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
 * A set declaring `requires: { layer: <name> }` can't function once that layer
 * is stripped, so removing the layer must cascade — the dependent set's packages
 * go to `replace` too. Models Luma → the Web API layer, which is locked on stock
 * Mage-OS (`removable: false`) but strippable under modulargento
 * (`removable_modulargento: true`).
 */
class LayerRequiresTest extends TestCase
{
    private function defs(): Definitions
    {
        return new Definitions(
            sets: [
                'luma' => [
                    'name' => 'luma',
                    'label' => 'Luma Theme',
                    'requires' => ['layer' => 'webapi'],
                    'packages' => ['acme/theme-frontend-luma'],
                ],
            ],
            layers: [
                'webapi' => [
                    'name' => 'webapi',
                    'label' => 'Web API',
                    'removable' => false,
                    'removable_modulargento' => true,
                    'packages' => ['acme/module-webapi'],
                ],
            ],
            addons: [], profileGroups: [], profiles: [],
        );
    }

    private function configurator(Definitions $defs): Configurator
    {
        $catalog = $this->createMock(CatalogRepository::class);
        $catalog->method('packageVersions')->willReturn([]);

        return new Configurator(
            $defs,
            $catalog,
            new AddonVersionResolver($defs, new ComposerRepoIndex([], 'mageos-catalog'), 'mageos-catalog'),
            'https://example.com/',
        );
    }

    public function test_set_required_layer_accessor(): void
    {
        $defs = $this->defs();
        $this->assertSame('webapi', $defs->setRequiredLayer('luma'));
        $this->assertNull($defs->setRequiredLayer('webapi'));
    }

    public function test_layer_removability_is_distribution_aware(): void
    {
        $defs = $this->defs();
        $this->assertFalse($defs->isLayerRemovable('webapi', 'standard'));
        $this->assertTrue($defs->isLayerRemovable('webapi', 'modulargento'));
    }

    public function test_locked_layer_on_standard_is_a_noop_and_keeps_dependent(): void
    {
        $defs = $this->defs();
        $cfg = $this->configurator($defs);
        // Web API layer requested off, but it's locked on standard Mage-OS:
        // neither it nor Luma may be stripped.
        $sel = new Selection('1.0.0', null, [], ['webapi'], [], [], []);

        $composer = $cfg->build($sel);
        $replace = $composer['replace'] ?? [];
        $this->assertArrayNotHasKey('acme/module-webapi', $replace);
        $this->assertArrayNotHasKey('acme/theme-frontend-luma', $replace, 'Luma must survive while Web API is force-kept');
    }

    public function test_disabling_layer_under_modulargento_cascades_to_dependent(): void
    {
        $defs = $this->defs();
        $cfg = $this->configurator($defs);
        // Under modulargento the Web API layer is strippable; Luma must follow.
        $sel = new Selection('1.0.0', null, [], ['webapi'], [], [], [], [], [], [], 'modulargento');

        $composer = $cfg->build($sel);
        $this->assertArrayHasKey('acme/module-webapi', $composer['replace']);
        $this->assertArrayHasKey('acme/theme-frontend-luma', $composer['replace'], 'Luma cascades off when its required layer is stripped');
    }
}
