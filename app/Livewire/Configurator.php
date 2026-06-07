<?php

namespace App\Livewire;

use App\Models\SavedConfig;
use App\Services\CatalogRepository;
use App\Services\ComposerJsonRenderer;
use App\Services\Configurator as ConfiguratorService;
use App\Services\Definitions;
use App\Services\InstallTreeResolver;
use App\Services\Selection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Full-page Livewire component that owns the configurator form state.
 *
 * The view models stock items as ENABLED-by-default (lists hold "currently checked"
 * items), which makes Livewire's array-style checkbox bindings the natural fit:
 * a checkbox is checked iff its value is in the bound array. The Selection passed
 * to ConfiguratorService is built in {@see Selection()} by computing the disabled
 * complements over the universe of stock names.
 */
class Configurator extends Component
{
    public ?string $version = null;

    /**
     * Version in effect before the most recent change, so {@see updatedVersion()}
     * can tell which version-gated sets just became available and default them on.
     */
    #[Locked]
    public ?string $previousVersion = null;

    public ?string $profile = null;

    /**
     * Which distribution backs the project: 'standard' (stock Mage-OS) or
     * 'modulargento' (fully-modular). Only selectable when {@see $version}
     * matches the configured modulargento version; forced to 'standard'
     * otherwise. Picking 'modulargento' unlocks the otherwise-locked sets.
     */
    public string $distribution = 'standard';

    /** @var list<string> Stock set names currently enabled (checked). */
    public array $enabledSets = [];

    /** @var list<string> Stock layer names currently enabled (checked). */
    public array $enabledStockLayers = [];

    /** @var list<string> Add-on names currently enabled (checked). Soft-defaults flow into here via {@see updatedProfileGroups()}. */
    public array $enabledAddons = [];

    /** @var array<string,string> Profile-group choices: ['theme' => 'luma', 'checkout' => 'default', ...] */
    public array $profileGroups = [];

    /** @var list<string> Subtoggle keys ("setName.subName") currently enabled. Universe minus this = disabled. */
    public array $enabledSubtoggles = [];

    /** @var list<string> Profile-group option subtoggle keys ("group.option[.variant].sub") currently enabled. Positive list. */
    public array $enabledOptionSubtoggles = [];

    /** @var array<string,string> Per-option variant pick: ['<group>.<option>' => '<variantName>']. Cleared on profile-group change so a re-snap to the theme-derived default fires. */
    public array $optionVariants = [];

    /** Last user-visible auto-snap message, e.g. "Checkout reset to default — Loki (Hyvä) requires the Hyvä theme." */
    public ?string $autoSnapNotice = null;

    public ?string $savedId = null;

    public ?string $savedAt = null;

    /**
     * Canonicalized snapshot of the selection at the moment the saved config
     * was loaded. Used to detect when the user has mutated state away from the
     * saved version — in which case the shareable `/c/{id}` link and "Saved"
     * indicator are no longer truthful and we fall back to the unsaved UX.
     */
    #[Locked]
    public ?string $savedSnapshot = null;

    // Hyvä credentials are intentionally NOT part of Selection — they must never be
    // persisted to SavedConfig or echoed back via the shared /c/{id} link.

    /** Hyvä packagist authentication token (free, obtained at hyva.io). */
    public string $hyvaToken = '';

    /** Hyvä packagist project name slug (the path component of your composer repo URL). */
    public string $hyvaProject = '';

    /**
     * Defaulted-addons list from the previous resolved state, persisted across
     * Livewire requests so we can diff and apply soft-default deltas.
     *
     * @var list<string>
     */
    #[Locked]
    public array $previousDefaultedAddons = [];

    public function mount(Definitions $defs, CatalogRepository $catalog, ConfiguratorService $configurator, ?string $id = null): void
    {
        if ($id !== null) {
            $cfg = SavedConfig::findOrFail($id);
            $sel = Selection::fromArray(
                array_merge($cfg->selection, ['version' => $cfg->mageos_version]),
                $cfg->mageos_version,
                $defs,
            );
            $this->savedId = $cfg->id;
            $this->savedAt = (string) $cfg->created_at;
        } else {
            $sel = Selection::default($catalog->latestStable(), $defs);
        }

        $this->hydrateFromSelection($sel, $defs, $configurator);

        if ($this->savedId !== null) {
            $this->savedSnapshot = $this->snapshotSelection();
        }
    }

    /**
     * Canonical JSON form of the current selection, used as the dirty-check
     * baseline against {@see $savedSnapshot}. Arrays are sorted so a checkbox
     * toggled off and back on doesn't read as dirty just because of insertion
     * order.
     */
    private function snapshotSelection(): string
    {
        $arr = $this->selection()->toArray();
        $sort = function (&$v) use (&$sort) {
            if (is_array($v)) {
                foreach ($v as &$inner) {
                    $sort($inner);
                }
                if (array_is_list($v)) {
                    sort($v);
                } else {
                    ksort($v);
                }
            }
        };
        $sort($arr);

        return json_encode($arr);
    }

    /**
     * Generic update hook — fires for any property change, including nested
     * array key updates (e.g. wire:model="profileGroups.theme"), where the
     * specific updatedProfileGroups() hook is not invoked by Livewire 4.
     *
     * Routes profile-group changes into the soft-default pass.
     */
    public function updated(string $name): void
    {
        if ($name === 'profileGroups' || str_starts_with($name, 'profileGroups.')) {
            $this->autoSnapInvalidOptions();
            // Variant picks are theme-derived by default; a profile-group change
            // should re-snap to the new theme's default rather than carry the
            // user's old override forward into a different context.
            $this->optionVariants = [];
            $this->syncOptionVariants();
            $this->reapplySoftDefaults();
        }
    }

    /**
     * Version picker changed. Sets are enabled-by-default, but a version-gated
     * set (e.g. RMA before 3.0.0) has no prior "checked" state from the versions
     * where it didn't exist — so when bumping into a version that ships it, add
     * it to the checked list so it defaults on rather than rendering unchecked
     * (which would silently drop a stock module).
     */
    public function updatedVersion(string $value): void
    {
        $defs = app(Definitions::class);
        $newlyAvailable = array_diff(
            array_keys($defs->setsForVersion($value)),
            array_keys($defs->setsForVersion($this->previousVersion ?? $value)),
        );
        $this->enabledSets = array_values(array_unique(array_merge($this->enabledSets, $newlyAvailable)));
        $this->previousVersion = $value;

        // The fully-modular flavor is only offered on its tracked version; if we
        // moved off it, snap back to the stock distribution and re-lock the sets
        // it had unlocked.
        if ($this->distribution === 'modulargento' && ! $this->modulargentoAvailable($value)) {
            $this->distribution = 'standard';
            $this->forceNonRemovableSetsOn();
        }
    }

    /** Whether the fully-modular (modulargento) distribution is offered for a version. */
    public function modulargentoAvailable(?string $version): bool
    {
        $mgVersion = (string) config('mageos.modulargento.version', '');

        return $mgVersion !== '' && $version === $mgVersion;
    }

    /**
     * Distribution radio changed. Re-lock any set that isn't removable under the
     * newly-selected distribution (switching modulargento → standard forces the
     * previously-unlocked sets back on so they aren't silently dropped).
     */
    public function updatedDistribution(string $value): void
    {
        if ($value === 'modulargento' && ! $this->modulargentoAvailable($this->version)) {
            $this->distribution = 'standard';
        }

        // Switching distribution changes which sets are removable, so re-derive
        // the set selection from the active profile: a profile like Lite that
        // wanted sets off but couldn't strip them under Standard should now
        // actually drop them under modulargento (and vice-versa). Only the sets
        // are recomputed — manual theme/checkout/add-on choices are preserved.
        $defs = app(Definitions::class);
        if ($this->profile !== null && isset($defs->profiles[$this->profile])) {
            $profileSel = Selection::default($this->version, $defs)
                ->applyProfile($defs->profiles[$this->profile]);
            $allSets = array_keys($defs->setsForVersion($this->version ?? ''));
            $effectiveDisabled = array_values(array_filter(
                $profileSel->disabledSets,
                fn ($n) => $defs->isSetRemovable($n, $this->distribution),
            ));
            $this->enabledSets = array_values(array_diff($allSets, $effectiveDisabled));
        }

        $this->forceNonRemovableSetsOn();
        $this->enforceSubtoggleRequires();
    }

    /**
     * Toggling a set may invalidate a subtoggle that depends on it (e.g. Page
     * Builder analytics requires the Analytics set). Re-run the requires pass so
     * the dependent subtoggle is force-disabled when its set is removed.
     */
    public function updatedEnabledSets(): void
    {
        $this->enforceSubtoggleRequires();
    }

    /**
     * Drop any enabled subtoggle whose `requires.set` is not currently enabled.
     * A subtoggle that builds on another set (Page Builder analytics → Analytics)
     * can't stand on its own, so it's removed from the positive list — which
     * sends its packages to `replace` — when that set is gone.
     */
    private function enforceSubtoggleRequires(): void
    {
        $defs = app(Definitions::class);
        $this->enabledSubtoggles = array_values(array_filter(
            $this->enabledSubtoggles,
            function ($key) use ($defs) {
                [$set, $sub] = array_pad(explode('.', $key, 2), 2, null);
                if ($sub === null) {
                    return true;
                }
                $needed = $defs->subtoggleRequiredSet($set, $sub);

                return $needed === null || in_array($needed, $this->enabledSets, true);
            },
        ));
    }

    /**
     * Ensure every set that is NOT removable under the current distribution is
     * checked (stock modules can't be dropped, so their checkbox must reflect
     * that they ship).
     */
    private function forceNonRemovableSetsOn(): void
    {
        $defs = app(Definitions::class);
        $allSets = array_keys($defs->setsForVersion($this->version ?? ''));
        $nonRemovable = array_values(array_filter(
            $allSets,
            fn ($n) => ! $defs->isSetRemovable($n, $this->distribution),
        ));
        $this->enabledSets = array_values(array_unique(array_merge($this->enabledSets, $nonRemovable)));
    }

    /**
     * Click handler for variant radios. wire:model can't bind to optionVariants
     * directly because the natural key contains a dot (e.g. "checkout.loki-checkout")
     * and Livewire interprets dots as nested-array path segments — the binding
     * would write/read the wrong key.
     */
    public function setOptionVariant(string $group, string $option, string $variant): void
    {
        $this->optionVariants["$group.$option"] = $variant;
    }

    /**
     * For every option that declares variants, write the resolved active variant
     * back into $optionVariants. Resolution prefers an existing user pick (when
     * its requires is met), so this only fills in defaults for unset/stale
     * entries; explicit picks are preserved.
     */
    private function syncOptionVariants(): void
    {
        $defs = app(Definitions::class);
        foreach ($defs->allVariantOptionKeys() as $key) {
            [$group, $option] = explode('.', $key, 2);
            $active = $defs->optionActiveVariant($group, $option, $this->profileGroups, $this->optionVariants);
            if ($active !== null) {
                $this->optionVariants[$key] = $active;
            }
        }
    }

    /**
     * After a profile-group change, walk every other group and snap it back to
     * its default if the previously-picked option's `requires` is no longer met.
     * Records a single user-facing notice when at least one snap happens.
     */
    private function autoSnapInvalidOptions(): void
    {
        $defs = app(Definitions::class);
        $messages = [];
        foreach ($this->profileGroups as $groupName => $optionName) {
            if ($defs->optionMeetsRequires($groupName, $optionName, $this->profileGroups)) {
                continue;
            }
            $default = $defs->defaultProfileGroupOption($groupName);
            if ($default === null || $default === $optionName) {
                continue;
            }
            $optionLabel = $defs->profileGroupOption($groupName, $optionName)['label'] ?? $optionName;
            $groupLabel = $defs->profileGroups[$groupName]['label'] ?? $groupName;
            $messages[] = "$groupLabel reset to default — $optionLabel is no longer compatible with the current selection.";
            $this->profileGroups[$groupName] = $default;
        }
        $this->autoSnapNotice = $messages !== [] ? implode(' ', $messages) : null;
    }

    /**
     * Diff new defaultedAddons against the previous tracker and add/remove
     * from $enabledAddons accordingly. (Bidirectional sync from the inverse
     * direction — toggling an addon back-to-update a profile-group — is
     * handled in updatedEnabledAddons().)
     */
    private function reapplySoftDefaults(): void
    {
        $defaulted = app(ConfiguratorService::class)->defaultedAddons($this->selection());

        $newlyDefaulted = array_values(array_diff($defaulted, $this->previousDefaultedAddons));
        $undefaulted = array_values(array_diff($this->previousDefaultedAddons, $defaulted));

        $this->enabledAddons = array_values(array_diff(
            array_unique(array_merge($this->enabledAddons, $newlyDefaulted)),
            $undefaulted,
        ));

        $this->previousDefaultedAddons = $defaulted;
    }

    /**
     * Bidirectional sync: when the user toggles an addon, snap the profile-group
     * radio that soft-defaults that addon to the matching option (or back to the
     * group's declared default if the user just removed an auto-defaulted item).
     *
     * @param  list<string>  $value  the new $enabledAddons array
     */
    public function updatedEnabledAddons(array $value, ?string $key = null): void
    {
        // Detect single addition/removal vs. previous state.
        $previous = $this->previousDefaultedAddons;
        $defs = app(Definitions::class);

        // Per-addon: was it just checked or unchecked?
        $beforeSet = $this->reconstructPreviousAddonSet($value);
        $added = array_values(array_diff($value, $beforeSet));
        $removed = array_values(array_diff($beforeSet, $value));

        foreach ($added as $name) {
            $this->snapGroupForAddonChange($defs, $name, true);
        }
        foreach ($removed as $name) {
            $this->snapGroupForAddonChange($defs, $name, false);
        }

        // Re-run the soft-default pass since profile-groups may have changed.
        if ($added || $removed) {
            $this->reapplySoftDefaults();
        }
    }

    /**
     * Reconstruct the addon set as it was before the user's toggle. We only know
     * the *new* array post-update; the diff is "which value moved by exactly one"
     * vs. the snapshot we just emitted to the client. To avoid storing extra
     * state, peek at the previous server-rendered state via the request payload's
     * "old" snapshot — but Livewire doesn't expose that directly. Easier: trust
     * the soft-default tracker to be one source, and the diff against profile-
     * group-defaulted addons to be another. For toggles where the item is in
     * `previousDefaultedAddons` ∩ new array → no change to detect; for items
     * that are NOT in either → it's a fresh user toggle.
     *
     * Practical heuristic: compute "expected" addon set if profile-groups didn't
     * change = (this->enabledAddons before update). Livewire passed us the new
     * value but the property in $this is already updated; we don't have the old
     * one. Workaround: maintain a parallel `previousEnabledAddons` snapshot on
     * the client.
     *
     * @param  list<string>  $current
     * @return list<string>
     */
    private function reconstructPreviousAddonSet(array $current): array
    {
        // Use the previous-defaulted list plus any items not in the new array
        // that were locked-in last cycle. In practice this approximation handles
        // the only case that matters for the bidirectional flip: a single toggle
        // that crosses the soft-default boundary. For multi-step changes the
        // sync converges within a couple of round-trips.
        return $this->previousEnabledAddons ?? $current;
    }

    /** @var list<string>|null Snapshot of taken at the start of each request, used by the diff logic in updatedEnabledAddons(). */
    private ?array $previousEnabledAddons = null;

    public function hydrate(): void
    {
        $this->previousEnabledAddons = $this->enabledAddons;
    }

    /**
     * For a single addon name, flip the profile-group radio that soft-defaults it
     * to follow the user's manual toggle.
     */
    private function snapGroupForAddonChange(Definitions $defs, string $name, bool $checked): void
    {
        foreach ($defs->profileGroups as $groupName => $def) {
            $current = $this->profileGroups[$groupName] ?? null;
            $currentOpt = collect($def['options'] ?? [])->firstWhere('name', $current);

            if (! $checked) {
                // User unchecked $name. If the currently-selected option soft-defaults it,
                // flip back to the group's default.
                if (! $currentOpt) {
                    continue;
                }
                $enables = $currentOpt['enables']['addons'] ?? [];
                if (! in_array($name, $enables, true)) {
                    continue;
                }
                $defaultOpt = collect($def['options'] ?? [])->firstWhere('default', true)
                    ?? ($def['options'][0] ?? null);
                if ($defaultOpt && $defaultOpt['name'] !== $current) {
                    $this->profileGroups[$groupName] = $defaultOpt['name'];
                }
            } else {
                // User checked $name. If some option in this group soft-defaults it
                // and isn't the current selection, snap to it.
                $wanted = collect($def['options'] ?? [])
                    ->first(fn ($opt) => in_array($name, $opt['enables']['addons'] ?? [], true));
                if ($wanted && $wanted['name'] !== $current) {
                    $this->profileGroups[$groupName] = $wanted['name'];
                }
            }
        }
    }

    /**
     * Top-level profile picker: rewrite all the form state from a profile YAML.
     */
    public function setProfile(string $name, Definitions $defs, ConfiguratorService $configurator): void
    {
        if (! isset($defs->profiles[$name])) {
            return;
        }
        $sel = Selection::default($this->version, $defs)->applyProfile($defs->profiles[$name]);
        // Carry the current distribution across the reseed so picking a profile
        // doesn't silently drop the fully-modular flavor — and so removability
        // (which sets actually get stripped) is evaluated for it.
        $sel = Selection::fromArray(
            ['distribution' => $this->distribution] + $sel->toArray(),
            $this->version,
            $defs,
        );
        $this->hydrateFromSelection($sel, $defs, $configurator);
    }

    public function save(ConfiguratorService $configurator)
    {
        $cfg = SavedConfig::create([
            'mageos_version' => $this->version,
            'selection' => $this->selection()->toArray(),
        ]);

        return $this->redirect(route('configurator.show', $cfg->id), navigate: true);
    }

    /**
     * Build a Selection from the current public state.
     *
     * Intentionally NOT a #[Computed] property: state can mutate multiple times
     * within a single request lifecycle (reapplySoftDefaults() runs after a
     * profile-group radio update and itself reads selection() to compute the new
     * defaultedAddons). Caching would hand back a stale Selection.
     */
    public function selection(): Selection
    {
        $defs = app(Definitions::class);
        // Only version-applicable sets participate: a version-gated set (e.g. RMA
        // before 3.0.0) must never be computed into disabledSets, or it would
        // emit a phantom replace entry for a package the base doesn't ship.
        $allSetNames = array_keys($defs->setsForVersion($this->version ?? ''));
        $stockLayerNames = array_values(array_filter(
            array_keys($defs->layers),
            fn ($n) => $defs->isLayerStock($n),
        ));

        $allSubtoggles = $defs->allSubtoggleKeys();

        // Sets / stock layers marked `removable: false` are force-enabled regardless
        // of UI state — they're known to break di:compile / setup:install when
        // removed. They never enter disabledSets / disabledLayers.
        $nonRemovableSets = array_values(array_filter($allSetNames, fn ($n) => ! $defs->isSetRemovable($n, $this->distribution)));
        $disabled = array_values(array_diff($allSetNames, $this->enabledSets, $nonRemovableSets));
        $nonRemovableLayers = array_values(array_filter($stockLayerNames, fn ($n) => ! $defs->isLayerRemovable($n)));
        $disabledLayers = array_values(array_diff($stockLayerNames, $this->enabledStockLayers, $nonRemovableLayers));

        return new Selection(
            version: $this->version ?? '',
            profile: $this->profile,
            disabledSets: $disabled,
            disabledLayers: $disabledLayers,
            enabledLayers: [],
            enabledAddons: $this->enabledAddons,
            profileGroups: $this->profileGroups,
            disabledSubtoggles: array_values(array_diff($allSubtoggles, $this->enabledSubtoggles)),
            enabledOptionSubtoggles: $this->enabledOptionSubtoggles,
            optionVariants: $this->optionVariants,
            distribution: $this->distribution,
        );
    }

    #[Computed]
    public function composer(): array
    {
        return app(ConfiguratorService::class)->build($this->selection(), $this->hyvaProject);
    }

    #[Computed]
    public function composerJson(): string
    {
        return app(ComposerJsonRenderer::class)->render($this->composer);
    }

    #[Computed]
    public function requireCount(): int
    {
        return count($this->composer['require'] ?? []);
    }

    #[Computed]
    public function replaceCount(): int
    {
        return count($this->composer['replace'] ?? []);
    }

    /**
     * The `--starter` argument for the "try it with bougie" callout. Once a
     * config is saved it's the shareable `/c/{id}` URL (bougie appends
     * `/starter.json` to a base URL, so the page URL resolves to this
     * config's manifest). On the unsaved default page it's the `mageos`
     * alias, which maps to the default starter — the note in the view says
     * as much and points the user at "Save & share" for their exact config.
     */
    #[Computed]
    public function starterArg(): string
    {
        return $this->effectiveSavedId !== null
            ? url("/c/{$this->effectiveSavedId}")
            : 'mageos';
    }

    /**
     * `$savedId` only counts while the current state still matches what was
     * saved. As soon as the user changes anything, the shareable link would
     * be a lie — bougie would resolve it to the old config — so we drop back
     * to the unsaved UX (default starter + "Save & share" hint).
     */
    #[Computed]
    public function effectiveSavedId(): ?string
    {
        if ($this->savedId === null || $this->savedSnapshot === null) {
            return $this->savedId;
        }

        return $this->snapshotSelection() === $this->savedSnapshot ? $this->savedId : null;
    }

    #[Computed]
    public function forcedAddons(): array
    {
        return app(ConfiguratorService::class)->forcedAddons($this->selection());
    }

    #[Computed]
    public function forcedLayers(): array
    {
        return app(ConfiguratorService::class)->forcedLayers($this->selection());
    }

    #[Computed]
    public function defaultedAddons(): array
    {
        return app(ConfiguratorService::class)->defaultedAddons($this->selection());
    }

    #[Computed]
    public function installTree(): array
    {
        return app(InstallTreeResolver::class)->resolve($this->selection());
    }

    /**
     * True iff the generated composer.json pulls in any hyva-themes/* package
     * (theme=hyva, hyva-checkout, etc.). Used to gate the install-instructions panel.
     */
    #[Computed]
    public function usesHyva(): bool
    {
        $defs = app(Definitions::class);
        $addonsInUse = array_unique(array_merge($this->enabledAddons, $this->forcedAddons));
        foreach ($addonsInUse as $name) {
            foreach ($defs->addonPackages($name) as $pkg) {
                if (str_starts_with($pkg, 'hyva-themes/')) {
                    return true;
                }
            }
        }

        return false;
    }

    private function hydrateFromSelection(Selection $sel, Definitions $defs, ConfiguratorService $configurator): void
    {
        $allSets = array_keys($defs->setsForVersion($sel->version));
        $stockLayers = array_values(array_filter(
            array_keys($defs->layers),
            fn ($n) => $defs->isLayerStock($n),
        ));

        $this->version = $sel->version;
        $this->previousVersion = $sel->version;
        $this->profile = $sel->profile;
        // A modulargento selection only stands when the version still offers it;
        // otherwise fall back to the stock distribution.
        $this->distribution = ($sel->distribution === 'modulargento' && $this->modulargentoAvailable($sel->version))
            ? 'modulargento'
            : 'standard';
        // Force non-removable sets on, even if a saved/profile selection tried to
        // disable them. The view also greys their checkboxes out. Removability
        // is distribution-aware (modulargento unlocks the otherwise-locked sets).
        $effectiveDisabled = array_values(array_filter(
            $sel->disabledSets,
            fn ($n) => $defs->isSetRemovable($n, $this->distribution),
        ));
        $this->enabledSets = array_values(array_diff($allSets, $effectiveDisabled));
        $effectiveDisabledLayers = array_values(array_filter(
            $sel->disabledLayers,
            fn ($n) => $defs->isLayerRemovable($n),
        ));
        $this->enabledStockLayers = array_values(array_diff($stockLayers, $effectiveDisabledLayers));
        $this->profileGroups = $sel->profileGroups;
        $this->enabledSubtoggles = array_values(array_diff($defs->allSubtoggleKeys(), $sel->disabledSubtoggles));
        $this->enabledOptionSubtoggles = $sel->enabledOptionSubtoggles;
        $this->optionVariants = $sel->optionVariants;
        $this->syncOptionVariants();
        // Apply soft defaults on top of the selection's explicit enabledAddons.
        $defaulted = $configurator->defaultedAddons($sel);
        $this->enabledAddons = array_values(array_unique(array_merge($sel->enabledAddons, $defaulted)));
        $this->previousDefaultedAddons = $defaulted;
        $this->enforceSubtoggleRequires();
    }

    public function render()
    {
        $defs = app(Definitions::class);
        $catalog = app(CatalogRepository::class);

        // Dispatch the freshly-computed JSON so the wire:ignore'd preview pane
        // can re-paint, re-highlight, and flash the diff client-side.
        $this->dispatch('composer-updated', json: $this->composerJson);

        // Partition sets by category so the view can render Modules and
        // Languages in separate panels. The underlying disable-by-replace
        // mechanism is unchanged — they're all just sets. Version-gated sets
        // (e.g. RMA before 3.0.0) are filtered out so they only appear for the
        // versions whose stock distribution actually ships them.
        $versionSets = $defs->setsForVersion($this->version ?? '');
        $modules = array_filter($versionSets, fn ($s) => ($s['category'] ?? 'module') === 'module');
        $languages = array_filter($versionSets, fn ($s) => ($s['category'] ?? 'module') === 'language');

        // Partition modules into the tabbed-workspace sub-categories (the `group`
        // field on each set's YAML). The order here drives the Modules section
        // layout; any module whose group isn't listed falls into "Other".
        $groupOrder = [
            'Catalog & Product Types' => 'Product types, swatches, reviews, wishlist',
            'Cart, Checkout & Orders' => 'MSI, multishipping, instant purchase',
            'Shipping, Tax & Payments' => 'DHL, FedEx, UPS, USPS, PayPal, FPT',
            'Marketing & Content' => 'Page Builder, Google, newsletter, storefront',
            'Security' => 'Two-Factor Auth, reCAPTCHA',
            'Admin, Ops & Developer' => 'Admin theme, RMA, analytics, S3, Swagger',
        ];
        $moduleGroups = [];
        foreach ($groupOrder as $label => $hint) {
            $moduleGroups[$label] = ['label' => $label, 'hint' => $hint, 'sets' => []];
        }
        foreach ($modules as $name => $set) {
            $label = $set['group'] ?? 'Other';
            if (! isset($moduleGroups[$label])) {
                $moduleGroups[$label] = ['label' => $label, 'hint' => '', 'sets' => []];
            }
            $moduleGroups[$label]['sets'][$name] = $set;
        }
        // Drop empty groups (e.g. a category whose only members are version-gated out).
        $moduleGroups = array_values(array_filter($moduleGroups, fn ($g) => $g['sets'] !== []));

        $setRemovable = [];
        foreach (array_keys($versionSets) as $name) {
            $setRemovable[$name] = $defs->isSetRemovable($name, $this->distribution);
        }
        $layerRemovable = [];
        foreach (array_keys($defs->layers) as $name) {
            $layerRemovable[$name] = $defs->isLayerRemovable($name);
        }

        return view('livewire.configurator', [
            'setDefs' => $modules,
            'moduleGroups' => $moduleGroups,
            'latestStable' => $catalog->latestStable(),
            'languageDefs' => $languages,
            'setRemovable' => $setRemovable,
            'layerRemovable' => $layerRemovable,
            'layerDefs' => $defs->layers,
            // Version-gated add-ons (e.g. RMA, opt-in only before 3.0.0) are
            // filtered out so they don't show alongside their set form.
            'addonDefs' => $defs->addonsForVersion($this->version ?? ''),
            'profileDefs' => $defs->profiles,
            'profileGroupDefs' => $defs->profileGroups,
            'versions' => $catalog->availableVersions() ?: [$this->version],
            // Whether to surface the Standard / Fully-modular distribution toggle
            // (only on the version the modulargento flavor tracks).
            'modulargentoAvailable' => $this->modulargentoAvailable($this->version),
        ])->layout('components.layouts.app');
    }
}
