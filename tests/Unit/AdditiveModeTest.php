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
            ],
            layers: [
                // A STOCK layer — subtractive by default, but addable in additive mode.
                'graphql' => ['name' => 'graphql', 'label' => 'GraphQL', 'packages' => ['mage-os/module-graph-ql', 'mage-os/module-catalog-graph-ql']],
            ],
            addons: [],
            profileGroups: [],
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
            ],
        );
    }

    private function selection(array $extra = []): Selection
    {
        return new Selection(
            version: '3.1.0', profile: null,
            disabledSets: [], disabledLayers: [], enabledAddons: [], profileGroups: [],
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
}
