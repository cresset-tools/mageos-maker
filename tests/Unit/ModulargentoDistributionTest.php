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
 * The fully-modular (modulargento) distribution is a flavor of a single Mage-OS
 * release: it swaps the backing Composer repo + edition package, unlocks the
 * sets that are forced-on in stock Mage-OS, and removes them by replacing the
 * `modulargento/*` packages (same kebab suffix, swapped vendor prefix).
 */
class ModulargentoDistributionTest extends TestCase
{
    private function defs(): Definitions
    {
        return new Definitions(
            sets: [
                // Locked in stock Mage-OS, decoupled (removable) under modulargento.
                'gift-message' => [
                    'name' => 'gift-message',
                    'label' => 'Gift Message',
                    'removable' => false,
                    'packages' => ['mage-os/module-gift-message', 'mage-os/module-gift-message-graph-ql'],
                ],
                // Opts out of modulargento removability even though decoupling exists.
                'core-thing' => [
                    'name' => 'core-thing',
                    'label' => 'Core Thing',
                    'removable' => false,
                    'removable_modulargento' => false,
                    'packages' => ['mage-os/module-core-thing'],
                ],
                // Removable under both distributions (an optional feature).
                'paypal' => [
                    'name' => 'paypal',
                    'label' => 'PayPal',
                    'packages' => ['mage-os/module-paypal'],
                ],
            ],
            layers: [],
            addons: [],
            profileGroups: [],
            profiles: [],
        );
    }

    private function configurator(Definitions $defs, array $modulargentoExtra = []): Configurator
    {
        $catalog = $this->createMock(CatalogRepository::class);
        $catalog->method('packageVersions')->willReturn([]);

        return new Configurator(
            $defs,
            $catalog,
            new AddonVersionResolver($defs, new ComposerRepoIndex([], 'mageos-catalog'), 'mageos-catalog'),
            'https://repo.mage-os.org/',
            array_merge([
                'repository_url' => 'https://modulargento.cresset.tools/',
                'edition_package' => 'modulargento/project-community-edition',
                'version' => '3.0.0',
                'php_constraint' => '~8.3.0||~8.4.0||~8.5.0',
            ], $modulargentoExtra),
        );
    }

    /** A stand-in for the shipped project-community-edition composer.json template. */
    private function projectTemplate(): array
    {
        return [
            'name' => 'modulargento/project-community-edition',
            'description' => 'Modulargento Community Edition Project',
            'type' => 'project',
            'require' => ['modulargento/product-community-edition' => '3.0.0'],
            'require-dev' => ['phpunit/phpunit' => '^11', 'phpstan/phpstan' => '^2'],
            'autoload' => ['psr-4' => ['Magento\\Setup\\' => 'setup/src/Magento/Setup/']],
            'autoload-dev' => ['psr-4' => ['Magento\\PhpStan\\' => 'dev/tests/static/framework/Magento/PhpStan/']],
            'extra' => ['magento-force' => 'override'],
            'license' => ['OSL-3.0', 'AFL-3.0'],
        ];
    }

    public function test_removability_is_distribution_aware(): void
    {
        $defs = $this->defs();

        // Stock: the set is locked on.
        $this->assertFalse($defs->isSetRemovable('gift-message', 'standard'));
        // Modulargento: it's decoupled, so removable.
        $this->assertTrue($defs->isSetRemovable('gift-message', 'modulargento'));
        // Explicit opt-out stays locked even under modulargento.
        $this->assertFalse($defs->isSetRemovable('core-thing', 'modulargento'));
    }

    public function test_modulargento_base_composer_uses_the_flavor_backing(): void
    {
        $cfg = $this->configurator($this->defs());

        $composer = $cfg->build(new Selection(
            version: '3.0.0', profile: null,
            disabledSets: [], disabledLayers: [], enabledLayers: [], enabledAddons: [], profileGroups: [],
            distribution: 'modulargento',
        ));

        // Root is named project-edition but requires the product edition
        // (a package can't require itself).
        $this->assertSame('modulargento/project-community-edition', $composer['name'] ?? null);
        $this->assertSame('3.0.0', $composer['require']['modulargento/product-community-edition'] ?? null);
        $this->assertArrayNotHasKey('modulargento/project-community-edition', $composer['require'] ?? []);
        $this->assertSame('~8.3.0||~8.4.0||~8.5.0', $composer['require']['php'] ?? null);
        $this->assertContains(
            ['type' => 'composer', 'url' => 'https://modulargento.cresset.tools/'],
            $composer['repositories'] ?? [],
        );
        // The stock mage-os repo must not leak into a modulargento project.
        $this->assertNotContains(
            ['type' => 'composer', 'url' => 'https://repo.mage-os.org/'],
            $composer['repositories'] ?? [],
        );
    }

    public function test_base_carries_every_key_from_the_published_project_template(): void
    {
        $cfg = $this->configurator($this->defs(), ['project_template' => $this->projectTemplate()]);

        $composer = $cfg->build(new Selection(
            version: '3.0.0', profile: null,
            disabledSets: [], disabledLayers: [], enabledLayers: [], enabledAddons: [], profileGroups: [],
            distribution: 'modulargento',
        ));

        // Keys carried verbatim from the real project composer.json.
        $this->assertSame(['OSL-3.0', 'AFL-3.0'], $composer['license'] ?? null);
        $this->assertArrayHasKey('phpunit/phpunit', $composer['require-dev'] ?? []);
        $this->assertArrayHasKey('Magento\\PhpStan\\', $composer['autoload-dev']['psr-4'] ?? []);
        $this->assertArrayHasKey('Magento\\Setup\\', $composer['autoload']['psr-4'] ?? []);
        $this->assertSame('override', $composer['extra']['magento-force'] ?? null);

        // Maker overlays applied on top.
        $this->assertSame('modulargento/project-community-edition', $composer['name'] ?? null);
        $this->assertSame('3.0.0', $composer['require']['modulargento/product-community-edition'] ?? null);
        $this->assertSame('~8.3.0||~8.4.0||~8.5.0', $composer['require']['php'] ?? null);
        $this->assertContains(
            ['type' => 'composer', 'url' => 'https://modulargento.cresset.tools/'],
            $composer['repositories'] ?? [],
        );
        $this->assertTrue($composer['config']['allow-plugins']['modulargento/*'] ?? false);
    }

    public function test_disabling_a_set_replaces_both_vendor_names(): void
    {
        $cfg = $this->configurator($this->defs());

        $composer = $cfg->build(new Selection(
            version: '3.0.0', profile: null,
            disabledSets: ['gift-message'], disabledLayers: [], enabledLayers: [], enabledAddons: [], profileGroups: [],
            distribution: 'modulargento',
        ));

        // A package may be installed as modulargento/* (renamed) or kept as
        // mage-os/* (the standalone forks), so both names are replaced — the
        // uninstalled one is a harmless no-op, guaranteeing removal either way.
        $this->assertArrayHasKey('modulargento/module-gift-message', $composer['replace'] ?? []);
        $this->assertArrayHasKey('mage-os/module-gift-message', $composer['replace'] ?? []);
        $this->assertArrayHasKey('modulargento/module-gift-message-graph-ql', $composer['replace'] ?? []);
        $this->assertArrayHasKey('mage-os/module-gift-message-graph-ql', $composer['replace'] ?? []);
    }

    public function test_standard_distribution_keeps_mage_os_replace_names(): void
    {
        $cfg = $this->configurator($this->defs());

        // A set removable on stock Mage-OS exercises the standard name path.
        $composer = $cfg->build(new Selection(
            version: '3.0.0', profile: null,
            disabledSets: ['paypal'], disabledLayers: [], enabledLayers: [], enabledAddons: [], profileGroups: [],
            distribution: 'standard',
        ));

        $this->assertArrayHasKey('mage-os/module-paypal', $composer['replace'] ?? []);
        $this->assertArrayNotHasKey('modulargento/module-paypal', $composer['replace'] ?? []);
    }

    public function test_build_skips_locked_sets_under_standard_but_strips_them_under_modulargento(): void
    {
        $cfg = $this->configurator($this->defs());

        // gift-message is removable: false. Disabling it on standard Mage-OS is a
        // no-op (can't be cleanly removed) so it must NOT enter the replace map —
        // this guards profiles that list locked sets for the modulargento case.
        $std = $cfg->build(new Selection(
            version: '3.0.0', profile: null,
            disabledSets: ['gift-message'], disabledLayers: [], enabledLayers: [], enabledAddons: [], profileGroups: [],
            distribution: 'standard',
        ));
        $this->assertArrayNotHasKey('mage-os/module-gift-message', $std['replace'] ?? []);
        $this->assertArrayNotHasKey('modulargento/module-gift-message', $std['replace'] ?? []);

        // The same disable under modulargento actually strips it.
        $mg = $cfg->build(new Selection(
            version: '3.0.0', profile: null,
            disabledSets: ['gift-message'], disabledLayers: [], enabledLayers: [], enabledAddons: [], profileGroups: [],
            distribution: 'modulargento',
        ));
        $this->assertArrayHasKey('modulargento/module-gift-message', $mg['replace'] ?? []);

        // A modulargento opt-out set stays put even under modulargento.
        $mgOptOut = $cfg->build(new Selection(
            version: '3.0.0', profile: null,
            disabledSets: ['core-thing'], disabledLayers: [], enabledLayers: [], enabledAddons: [], profileGroups: [],
            distribution: 'modulargento',
        ));
        $this->assertArrayNotHasKey('modulargento/module-core-thing', $mgOptOut['replace'] ?? []);
        $this->assertArrayNotHasKey('mage-os/module-core-thing', $mgOptOut['replace'] ?? []);
    }
}
