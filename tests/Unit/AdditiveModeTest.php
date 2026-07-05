<?php

namespace Tests\Unit;

use App\Services\AddonVersionResolver;
use App\Services\CatalogRepository;
use App\Services\ComposerRepoIndex;
use App\Services\Configurator;
use App\Services\Definitions;
use App\Services\Selection;
use Tests\TestCase;

/**
 * Additive ("inverse") mode: start from the minimal edition and `require` the
 * enabled sets/layers/add-ons, instead of the full edition + `replace`. No
 * `replace` is emitted, and the minimal base gets the laminas/laminas-view
 * di:compile fix injected (config `mageos.minimal_base_extra_require`).
 */
class AdditiveModeTest extends TestCase
{
    private function defs(): Definitions
    {
        return new Definitions(
            sets: [
                'paypal' => ['name' => 'paypal', 'label' => 'PayPal', 'packages' => ['mage-os/module-paypal', 'mage-os/module-paypal-graph-ql']],
                'bundle' => ['name' => 'bundle', 'label' => 'Bundle', 'packages' => ['mage-os/module-bundle']],
                // A set whose module ships as a STANDALONE fork under modulargento
                // (keeps its mage-os vendor there, unlike the lockstep monorepo).
                'page-builder' => ['name' => 'page-builder', 'label' => 'Page Builder', 'packages' => ['mage-os/module-page-builder-widget']],
                // A set with a subtoggle whose payload arrives transitively.
                'two-factor-auth' => [
                    'name' => 'two-factor-auth', 'label' => '2FA',
                    'packages' => ['mage-os/module-two-factor-auth'],
                    'subtoggles' => [
                        ['name' => 'duo', 'label' => 'Duo', 'packages' => ['duosecurity/duo_api_php']],
                    ],
                ],
            ],
            layers: [
                // A STOCK layer — subtractive by default, but addable in additive mode.
                'graphql' => ['name' => 'graphql', 'label' => 'GraphQL', 'packages' => ['mage-os/module-graph-ql', 'mage-os/module-catalog-graph-ql']],
            ],
            addons: [],
            profileGroups: [
                'features' => [
                    'name' => 'features', 'label' => 'Features',
                    'options' => [
                        // The additive mirror of `disables.sets`.
                        ['name' => 'with-paypal', 'label' => 'With PayPal', 'enables' => ['sets' => ['paypal']]],
                    ],
                ],
            ],
            profiles: [],
        );
    }

    private function configurator(Definitions $defs): Configurator
    {
        $catalog = $this->createMock(CatalogRepository::class);
        $catalog->method('packageVersions')->willReturn([]); // empty catalog → hand-built fallback base

        return new Configurator(
            $defs,
            $catalog,
            new AddonVersionResolver($defs, new ComposerRepoIndex([], 'mageos-catalog'), 'mageos-catalog'),
            'https://repo.mage-os.org/',
            [
                'repository_url' => 'https://modulargento.cresset.tools/',
                'edition_package' => 'modulargento/project-community-edition',
                'version' => '3.1.0',
                'php_constraint' => '~8.4.0',
                'standalone_packages' => ['mage-os/module-page-builder-widget'],
            ],
        );
    }

    private function selection(array $extra = []): Selection
    {
        return new Selection(
            version: '3.1.0', profile: null,
            disabledSets: [], disabledLayers: [], enabledAddons: [],
            profileGroups: $extra['profileGroups'] ?? [],
            disabledSubtoggles: $extra['disabledSubtoggles'] ?? [],
            distribution: $extra['distribution'] ?? 'standard',
            mode: $extra['mode'] ?? 'additive',
            enabledSets: $extra['enabledSets'] ?? [],
            enabledLayers: $extra['enabledLayers'] ?? [],
        );
    }

    public function test_additive_build_uses_the_minimal_base_and_injects_laminas_view(): void
    {
        $composer = $this->configurator($this->defs())->build($this->selection());

        $this->assertSame('mage-os/project-minimal-edition', $composer['name'] ?? null);
        $this->assertSame('3.1.0', $composer['require']['mage-os/product-minimal-edition'] ?? null);
        // The di:compile fix — minimal edition drops laminas-view, we add it back.
        $this->assertArrayHasKey('laminas/laminas-view', $composer['require'] ?? []);
        // Additive never emits replace.
        $this->assertArrayNotHasKey('replace', $composer);
    }

    public function test_enabled_sets_go_to_require_pinned_to_the_edition_version(): void
    {
        $composer = $this->configurator($this->defs())->build($this->selection(['enabledSets' => ['paypal']]));

        $this->assertSame('3.1.0', $composer['require']['mage-os/module-paypal'] ?? null);
        $this->assertSame('3.1.0', $composer['require']['mage-os/module-paypal-graph-ql'] ?? null);
        $this->assertArrayNotHasKey('replace', $composer);
    }

    public function test_enabled_stock_layer_is_required_in_additive_but_a_noop_in_subtractive(): void
    {
        $cfg = $this->configurator($this->defs());

        // Additive: the minimal base lacks graphql, so enabling it requires its packages.
        $add = $cfg->build($this->selection(['enabledLayers' => ['graphql']]));
        $this->assertSame('3.1.0', $add['require']['mage-os/module-graph-ql'] ?? null);
        $this->assertSame('3.1.0', $add['require']['mage-os/module-catalog-graph-ql'] ?? null);

        // Subtractive: a stock layer is already in the full base — enabling it is a
        // no-op (nothing to require), it's just "not disabled".
        $sub = $cfg->build($this->selection(['mode' => 'subtractive', 'enabledLayers' => ['graphql']]));
        $this->assertArrayNotHasKey('mage-os/module-graph-ql', $sub['require'] ?? []);
    }

    public function test_additive_modulargento_requires_the_modulargento_vendor(): void
    {
        $composer = $this->configurator($this->defs())->build($this->selection([
            'distribution' => 'modulargento',
            'enabledSets' => ['paypal'],
        ]));

        // Base swaps to the modulargento minimal edition; features require modulargento/*.
        $this->assertSame('3.1.0', $composer['require']['modulargento/product-minimal-edition'] ?? null);
        $this->assertArrayHasKey('modulargento/module-paypal', $composer['require'] ?? []);
        $this->assertArrayNotHasKey('mage-os/module-paypal', $composer['require'] ?? []);
        $this->assertArrayNotHasKey('replace', $composer);
    }

    public function test_selection_round_trips_mode_and_enabled_sets(): void
    {
        $sel = Selection::fromArray(
            ['version' => '3.1.0', 'mode' => 'additive', 'enabledSets' => ['paypal', 'bundle']],
            '3.1.0',
            $this->defs(),
        );

        $this->assertTrue($sel->isAdditive());
        $this->assertSame(['paypal', 'bundle'], $sel->enabledSets);
        $this->assertSame('additive', $sel->toArray()['mode']);
        $this->assertSame(['paypal', 'bundle'], $sel->toArray()['enabledSets']);
    }

    public function test_from_array_rejects_an_unknown_mode(): void
    {
        // A typo'd mode must fail loudly — silently matching nothing in
        // isAdditive() would hand back a full subtractive build.
        $this->expectException(\InvalidArgumentException::class);

        Selection::fromArray(['version' => '3.1.0', 'mode' => 'addative'], '3.1.0', $this->defs());
    }

    public function test_additive_modulargento_keeps_standalone_fork_names(): void
    {
        // Standalone forks (config: modulargento.standalone_packages) are served
        // UN-renamed by the modulargento repo — requiring modulargento/* for
        // them would point at a package that doesn't exist.
        $composer = $this->configurator($this->defs())->build($this->selection([
            'distribution' => 'modulargento',
            'enabledSets' => ['paypal', 'page-builder'],
        ]));

        $this->assertArrayHasKey('modulargento/module-paypal', $composer['require'] ?? []);
        // Standalone forks version independently (1.x, own tags), so they take
        // any served version instead of the edition pin — in both distributions.
        $this->assertSame('*', $composer['require']['mage-os/module-page-builder-widget'] ?? null);
        $this->assertArrayNotHasKey('modulargento/module-page-builder-widget', $composer['require'] ?? []);

        $standard = $this->configurator($this->defs())->build($this->selection([
            'enabledSets' => ['page-builder'],
        ]));
        $this->assertSame('*', $standard['require']['mage-os/module-page-builder-widget'] ?? null);
    }

    public function test_profile_group_option_can_enable_sets_in_additive_mode(): void
    {
        // enables.sets is the additive mirror of disables.sets: the option pulls
        // the set into require without the user ticking it explicitly.
        $composer = $this->configurator($this->defs())->build($this->selection([
            'profileGroups' => ['features' => 'with-paypal'],
        ]));

        $this->assertSame('3.1.0', $composer['require']['mage-os/module-paypal'] ?? null);
    }

    public function test_additive_disabled_subtoggle_of_an_enabled_set_goes_to_replace(): void
    {
        // The set's module pulls its subtoggle payload transitively, so a
        // disabled subtoggle is the one case where additive emits replace.
        $composer = $this->configurator($this->defs())->build($this->selection([
            'enabledSets' => ['two-factor-auth'],
            'disabledSubtoggles' => ['two-factor-auth.duo'],
        ]));

        $this->assertSame('3.1.0', $composer['require']['mage-os/module-two-factor-auth'] ?? null);
        $this->assertSame('*', $composer['replace']['duosecurity/duo_api_php'] ?? null);

        // A disabled subtoggle of a NOT-enabled set stays out of replace — its
        // parent module is never required, so nothing arrives to strip.
        $without = $this->configurator($this->defs())->build($this->selection([
            'disabledSubtoggles' => ['two-factor-auth.duo'],
        ]));
        $this->assertArrayNotHasKey('replace', $without);
    }

    public function test_minimal_included_and_partial_sets_derive_from_the_catalog(): void
    {
        $defs = $this->defs();
        $catalog = $this->createMock(CatalogRepository::class);
        $catalog->method('packageVersions')->willReturnCallback(fn (string $name) => $name === 'mage-os/product-minimal-edition'
            ? ['3.1.0' => ['require' => [
                'mage-os/module-bundle' => '3.1.0',
                'mage-os/module-two-factor-auth' => '3.1.0',
                'mage-os/module-paypal' => '3.1.0', // its graph-ql companion is absent → partial
            ]]]
            : []);
        $cfg = new Configurator(
            $defs,
            $catalog,
            new AddonVersionResolver($defs, new ComposerRepoIndex([], 'mageos-catalog'), 'mageos-catalog'),
            'https://repo.mage-os.org/',
            [
                'minimal_edition_package' => 'modulargento/project-minimal-edition',
                'minimal_removed_sets' => ['bundle'],
            ],
        );

        $this->assertSame(['bundle', 'two-factor-auth'], $cfg->minimalIncludedSets('standard', '3.1.0'));
        $this->assertSame(['paypal'], $cfg->minimalPartialSets('standard', '3.1.0'));
        // The leaner modulargento minimal drops the config-listed sets from the keep-set.
        $this->assertSame(['two-factor-auth'], $cfg->minimalIncludedSets('modulargento', '3.1.0'));
        // GraphQL layer modules aren't in the minimal base at all — neither included nor partial.
        $this->assertSame([], $cfg->minimalIncludedLayers('standard', '3.1.0'));
        $this->assertSame([], $cfg->minimalPartialLayers('standard', '3.1.0'));
        // No catalog metadata for the version → additive isn't offered (null).
        $this->assertNull($cfg->minimalIncludedSets('standard', '3.0.0'));
    }

    public function test_minimal_edition_package_availability(): void
    {
        $cfg = $this->configurator($this->defs());

        // Standard reads config('mageos.minimal_edition_package').
        $this->assertSame('mage-os/project-minimal-edition', $cfg->minimalEditionPackage('standard', '3.1.0'));
        // The test's modulargento config wires no minimal edition → unavailable.
        $this->assertNull($cfg->minimalEditionPackage('modulargento', '3.1.0'));
    }

    public function test_wired_modulargento_minimal_edition_uses_its_template_base(): void
    {
        // The real config shape: minimal_edition_package + minimal_project_template
        // wired per version (the provider decodes *_template_path files into these).
        $defs = $this->defs();
        $catalog = $this->createMock(CatalogRepository::class);
        $catalog->method('packageVersions')->willReturn([]);
        $cfg = new Configurator(
            $defs,
            $catalog,
            new AddonVersionResolver($defs, new ComposerRepoIndex([], 'mageos-catalog'), 'mageos-catalog'),
            'https://repo.mage-os.org/',
            [
                'repository_url' => 'https://modulargento.cresset.tools/',
                'edition_package' => 'modulargento/project-community-edition',
                'versions' => [
                    '3.1.0' => [
                        'php_constraint' => '~8.4.0',
                        'minimal_edition_package' => 'modulargento/project-minimal-edition',
                        'minimal_project_template' => [
                            'name' => 'modulargento/project-minimal-edition',
                            'require' => ['modulargento/product-minimal-edition' => '3.1.0'],
                            'license' => ['OSL-3.0'],
                            'type' => 'project',
                        ],
                    ],
                ],
            ],
        );

        $this->assertSame('modulargento/project-minimal-edition', $cfg->minimalEditionPackage('modulargento', '3.1.0'));

        $composer = $cfg->build($this->selection(['distribution' => 'modulargento']));
        // Template-based base: carries the template's keys, the maker's repo +
        // php constraint overlay, and the laminas-view minimal fix.
        $this->assertSame('modulargento/project-minimal-edition', $composer['name'] ?? null);
        $this->assertSame('3.1.0', $composer['require']['modulargento/product-minimal-edition'] ?? null);
        $this->assertSame(['OSL-3.0'], $composer['license'] ?? null);
        $this->assertSame('~8.4.0', $composer['require']['php'] ?? null);
        $this->assertArrayHasKey('laminas/laminas-view', $composer['require'] ?? []);
    }
}
