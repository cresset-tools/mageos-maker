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
 * Sets may declare a `since:` version (inclusive lower bound). Such a set only
 * applies — and only emits a `replace` entry when disabled — for Mage-OS
 * versions at or above that bound. Models RMA, bundled in stock from 3.0.0.
 */
class VersionGatedSetTest extends TestCase
{
    private function defs(): Definitions
    {
        return new Definitions(
            sets: [
                'mageos-rma' => [
                    'name' => 'mageos-rma',
                    'label' => 'Mage-OS RMA',
                    'since' => '3.0.0',
                    'packages' => ['mage-os/module-rma'],
                ],
                'paypal' => [
                    'name' => 'paypal',
                    'label' => 'PayPal',
                    'packages' => ['mage-os/module-paypal'],
                ],
            ],
            layers: [], addons: [], profileGroups: [], profiles: [],
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

    public function test_is_set_available_respects_since_bound(): void
    {
        $defs = $this->defs();

        $this->assertFalse($defs->isSetAvailable('mageos-rma', '2.2.2'));
        $this->assertTrue($defs->isSetAvailable('mageos-rma', '3.0.0'));
        $this->assertTrue($defs->isSetAvailable('mageos-rma', '3.1.0'));
        // Sets without a `since` apply to every version.
        $this->assertTrue($defs->isSetAvailable('paypal', '2.2.2'));
    }

    public function test_sets_for_version_filters_gated_sets(): void
    {
        $defs = $this->defs();

        $this->assertArrayNotHasKey('mageos-rma', $defs->setsForVersion('2.2.2'));
        $this->assertArrayHasKey('mageos-rma', $defs->setsForVersion('3.0.0'));
        // Non-gated sets are always present.
        $this->assertArrayHasKey('paypal', $defs->setsForVersion('2.2.2'));
    }

    public function test_disabling_gated_set_emits_replace_only_when_available(): void
    {
        $defs = $this->defs();
        $cfg = $this->configurator($defs);

        // 3.0.0: RMA ships in stock, so disabling it strips the module.
        $on = $cfg->build(new Selection('3.0.0', null, ['mageos-rma'], [], [], [], []));
        $this->assertArrayHasKey('mage-os/module-rma', $on['replace'] ?? []);

        // 2.2.2: RMA isn't in stock — disabling is a no-op, no phantom replace.
        $off = $cfg->build(new Selection('2.2.2', null, ['mageos-rma'], [], [], [], []));
        $this->assertArrayNotHasKey('mage-os/module-rma', $off['replace'] ?? []);
    }
}
