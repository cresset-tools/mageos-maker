<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Serializes the full {@see Definitions} (plus version/catalog metadata and the
 * initial {@see Selection}) into one JSON-able blob embedded in the configurator
 * page as `window.MAKER`. The client-side derivation engine
 * (resources/js/maker-engine.js) reads this and reproduces the same rules the
 * PHP engine applies, so every toggle is instant — the server is only re-hit to
 * generate the authoritative composer.json + install tree.
 *
 * This carries *rules and labels*, never package version constraints: the real
 * composer.json always comes back from {@see Configurator::build()}.
 */
class DefinitionsSerializer
{
    /**
     * The Modules section's category ordering + one-line hints — mirrors the
     * `$groupOrder` map the old Livewire `render()` built. Any module whose
     * `group` isn't listed falls into "Other".
     */
    private const GROUP_ORDER = [
        'Catalog & Product Types' => 'Product types, swatches, reviews, wishlist',
        'Cart, Checkout & Orders' => 'MSI, multishipping, instant purchase',
        'Shipping, Tax & Payments' => 'DHL, FedEx, UPS, USPS, PayPal, FPT',
        'Marketing & Content' => 'Page Builder, Google, newsletter, storefront',
        'Security' => 'Two-Factor Auth, reCAPTCHA',
        'Admin, Ops & Developer' => 'Admin theme, RMA, analytics, S3, Swagger',
    ];

    public function __construct(
        private readonly Definitions $defs,
        private readonly CatalogRepository $catalog,
    ) {}

    /**
     * @param  array{savedId?:?string, savedAt?:?string, savedSnapshot?:?string}  $savedMeta
     * @return array<string,mixed>
     */
    public function payload(Selection $initial, array $savedMeta = []): array
    {
        return [
            'versions' => array_values($this->catalog->availableVersions()),
            'latestStable' => $this->catalog->latestStable(),
            'modulargentoVersions' => array_values(array_map(
                'strval',
                array_keys((array) config('mageos.modulargento.versions', [])),
            )),
            'defaultProfile' => $this->defs->defaultProfile(),
            'profiles' => $this->profiles(),
            'profileGroups' => array_values($this->defs->profileGroups),
            'sets' => $this->sets(),
            'moduleGroupOrder' => array_map(
                fn ($label, $hint) => ['label' => $label, 'hint' => $hint],
                array_keys(self::GROUP_ORDER),
                array_values(self::GROUP_ORDER),
            ),
            'languages' => $this->languages(),
            'layers' => $this->layers(),
            'addons' => $this->addons(),
            'initial' => [
                'selection' => $initial->toArray(),
                'savedId' => $savedMeta['savedId'] ?? null,
                'savedAt' => $savedMeta['savedAt'] ?? null,
                'savedSnapshot' => $savedMeta['savedSnapshot'] ?? null,
            ],
        ];
    }

    /** @return array<string,array<string,mixed>> */
    private function profiles(): array
    {
        $out = [];
        foreach ($this->defs->profiles as $name => $p) {
            // Additive-mode profiles are CLI/API-only until the Build Canvas
            // grows a mode toggle: the client engine has no additive rendering
            // and drops mode/enabledSets from its build posts, so surfacing
            // e.g. mageos-minimal here would silently produce a full
            // subtractive build — the opposite of what the card advertises.
            if (($p['selection']['mode'] ?? 'subtractive') === 'additive') {
                continue;
            }
            $out[$name] = [
                'name' => $name,
                'label' => $p['label'] ?? $name,
                'description' => $p['description'] ?? '',
                'default' => (bool) ($p['default'] ?? false),
                'selection' => $p['selection'] ?? [],
            ];
        }

        return $out;
    }

    /**
     * Every set (the client gates by version itself via since/until). Carries
     * only the fields the engine needs to derive on/off + origin + greying.
     *
     * @return array<string,array<string,mixed>>
     */
    private function sets(): array
    {
        $out = [];
        foreach ($this->defs->sets as $name => $s) {
            $out[$name] = [
                'name' => $name,
                'label' => $s['label'] ?? $name,
                'description' => $s['description'] ?? '',
                'category' => $s['category'] ?? 'module',
                'group' => $s['group'] ?? 'Other',
                'since' => $s['since'] ?? null,
                'until' => $s['until'] ?? null,
                'removable' => ($s['removable'] ?? true) !== false,
                'removable_modulargento' => ($s['removable_modulargento'] ?? true) !== false,
                'requires' => [
                    'set' => $s['requires']['set'] ?? null,
                    'layer' => $s['requires']['layer'] ?? null,
                ],
                'subtoggles' => array_map(fn ($sub) => [
                    'name' => $sub['name'],
                    'label' => $sub['label'] ?? $sub['name'],
                    'description' => $sub['description'] ?? '',
                    'requires' => ['set' => $sub['requires']['set'] ?? null],
                ], $s['subtoggles'] ?? []),
            ];
        }

        return $out;
    }

    /**
     * Language sets keyed by set name, with the locale code/label derived from
     * the `language-<locale>` name — mirrors the old blade's derivation.
     *
     * @return array<string,array{label:string,code:string,locale:string}>
     */
    private function languages(): array
    {
        $out = [];
        foreach ($this->defs->sets as $name => $s) {
            if (($s['category'] ?? 'module') !== 'language') {
                continue;
            }
            $loc = Str::after($name, 'language-');
            $parts = explode('_', $loc);
            $locale = collect($parts)
                ->map(fn ($p, $i) => $i === 0 ? $p : (strlen($p) === 2 ? strtoupper($p) : ucfirst($p)))
                ->implode('_');
            $out[$name] = [
                'label' => trim(Str::before($s['label'] ?? $name, '(')),
                'code' => $parts[0],
                'locale' => $locale,
            ];
        }

        return $out;
    }

    /** @return array<string,array<string,mixed>> */
    private function layers(): array
    {
        $out = [];
        foreach ($this->defs->layers as $name => $l) {
            $out[$name] = [
                'name' => $name,
                'label' => $l['label'] ?? $name,
                'description' => $l['description'] ?? '',
                'stock' => ($l['stock'] ?? true) !== false,
                'removable' => ($l['removable'] ?? true) !== false,
                'removable_modulargento' => ($l['removable_modulargento'] ?? true) !== false,
            ];
        }

        return $out;
    }

    /**
     * Add-ons keyed by name. `hyva` flags add-ons that pull a `hyva-themes/*`
     * package so the client can optimistically reveal the Hyvä dock tab;
     * `lokiHyva` likewise flags the Hyvä build of Loki Checkout so the client
     * can reveal its setup panel. Both are authoritatively confirmed by the
     * value that comes back from /api/build.
     *
     * @return array<string,array<string,mixed>>
     */
    private function addons(): array
    {
        $out = [];
        foreach ($this->defs->addons as $name => $a) {
            $hyva = false;
            $lokiHyva = false;
            foreach ($this->defs->addonPackages($name) as $pkg) {
                if (str_starts_with((string) $pkg, 'hyva-themes/')) {
                    $hyva = true;
                }
                if ($pkg === 'loki-checkout/magento2-hyva') {
                    $lokiHyva = true;
                }
            }
            $out[$name] = [
                'name' => $name,
                'label' => $a['label'] ?? $name,
                'description' => $a['description'] ?? '',
                'since' => $a['since'] ?? null,
                'until' => $a['until'] ?? null,
                'hyva' => $hyva,
                'lokiHyva' => $lokiHyva,
            ];
        }

        return $out;
    }
}
