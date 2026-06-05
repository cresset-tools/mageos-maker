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
 * Sets and add-ons may declare `since` (inclusive lower bound) / `until`
 * (exclusive upper bound) version gates. This lets one feature be modeled two
 * ways across the version line — RMA is an opt-in add-on before 3.0.0, then a
 * default-on removable set from 3.0.0 (when the metapackage bundles it).
 */
class VersionGatedSetTest extends TestCase
{
    private function defs(): Definitions
    {
        return new Definitions(
            sets: [
                // RMA bundled in stock from 3.0.0 → default-on removable set.
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
            layers: [],
            addons: [
                // Same feature, opt-in form for the versions that don't bundle it.
                'mageos-rma' => [
                    'name' => 'mageos-rma',
                    'label' => 'Mage-OS RMA',
                    'until' => '3.0.0',
                    'packages' => ['mage-os/module-rma'],
                ],
            ],
            profileGroups: [], profiles: [],
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

    public function test_set_since_bound(): void
    {
        $defs = $this->defs();

        $this->assertFalse($defs->isSetAvailable('mageos-rma', '2.3.0'));
        $this->assertTrue($defs->isSetAvailable('mageos-rma', '3.0.0'));
        $this->assertTrue($defs->isSetAvailable('mageos-rma', '3.1.0'));
        // Sets without a bound apply to every version.
        $this->assertTrue($defs->isSetAvailable('paypal', '2.3.0'));
    }

    public function test_addon_until_bound(): void
    {
        $defs = $this->defs();

        $this->assertTrue($defs->isAddonAvailable('mageos-rma', '2.2.2'));
        $this->assertTrue($defs->isAddonAvailable('mageos-rma', '2.3.0'));
        // `until` is exclusive — at the bound it's the set's job, not the add-on's.
        $this->assertFalse($defs->isAddonAvailable('mageos-rma', '3.0.0'));
    }

    public function test_set_and_addon_partition_cleanly_at_the_bound(): void
    {
        $defs = $this->defs();

        // Below 3.0.0: add-on offered, set hidden.
        $this->assertArrayHasKey('mageos-rma', $defs->addonsForVersion('2.3.0'));
        $this->assertArrayNotHasKey('mageos-rma', $defs->setsForVersion('2.3.0'));

        // From 3.0.0: set shown, add-on hidden.
        $this->assertArrayNotHasKey('mageos-rma', $defs->addonsForVersion('3.0.0'));
        $this->assertArrayHasKey('mageos-rma', $defs->setsForVersion('3.0.0'));
    }

    public function test_enabling_rma_addon_adds_require_only_below_the_bound(): void
    {
        $defs = $this->defs();
        $cfg = $this->configurator($defs);

        // 2.3.0: opt-in add-on → module-rma in require.
        $old = $cfg->build(new Selection('2.3.0', null, [], [], [], ['mageos-rma'], []));
        $this->assertArrayHasKey('mage-os/module-rma', $old['require']);

        // 3.0.0: the add-on doesn't apply (it's a bundled set there); a stale
        // enabledAddons entry must not re-add it to require.
        $new = $cfg->build(new Selection('3.0.0', null, [], [], [], ['mageos-rma'], []));
        $this->assertArrayNotHasKey('mage-os/module-rma', $new['require'] ?? []);
    }

    public function test_disabling_rma_set_emits_replace_only_at_or_above_the_bound(): void
    {
        $defs = $this->defs();
        $cfg = $this->configurator($defs);

        // 3.0.0: RMA ships in stock, so disabling the set strips the module.
        $on = $cfg->build(new Selection('3.0.0', null, ['mageos-rma'], [], [], [], []));
        $this->assertArrayHasKey('mage-os/module-rma', $on['replace'] ?? []);

        // 2.3.0: RMA isn't in stock — disabling the set is a no-op, no phantom replace.
        $off = $cfg->build(new Selection('2.3.0', null, ['mageos-rma'], [], [], [], []));
        $this->assertArrayNotHasKey('mage-os/module-rma', $off['replace'] ?? []);
    }
}
