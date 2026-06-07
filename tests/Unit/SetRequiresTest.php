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
 * A set declaring `requires: { set: <name> }` can't function once the required
 * set is removed, so disabling the required set must cascade — the dependent
 * set's packages go to `replace` too. Models Luma → Web API.
 */
class SetRequiresTest extends TestCase
{
    private function defs(): Definitions
    {
        return new Definitions(
            sets: [
                'webapi' => [
                    'name' => 'webapi',
                    'label' => 'Web API',
                    'packages' => ['acme/module-webapi'],
                ],
                'luma' => [
                    'name' => 'luma',
                    'label' => 'Luma Theme',
                    'requires' => ['set' => 'webapi'],
                    'packages' => ['acme/theme-frontend-luma'],
                ],
                // A second dependent to prove the cascade reaches a chain (luma → webapi,
                // and this one → luma).
                'luma-child' => [
                    'name' => 'luma-child',
                    'label' => 'Luma Child',
                    'requires' => ['set' => 'luma'],
                    'packages' => ['acme/theme-child'],
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

    public function test_set_required_set_accessor(): void
    {
        $defs = $this->defs();
        $this->assertSame('webapi', $defs->setRequiredSet('luma'));
        $this->assertNull($defs->setRequiredSet('webapi'));
    }

    public function test_disabling_required_set_cascades_to_dependent(): void
    {
        $defs = $this->defs();
        $cfg = $this->configurator($defs);
        // Only webapi explicitly disabled; luma + luma-child must follow.
        $sel = new Selection('1.0.0', null, ['webapi'], [], [], [], [], []);

        $composer = $cfg->build($sel);
        $this->assertArrayHasKey('acme/module-webapi', $composer['replace']);
        $this->assertArrayHasKey('acme/theme-frontend-luma', $composer['replace']);
        $this->assertArrayHasKey('acme/theme-child', $composer['replace'], 'cascade should reach the chain');
    }

    public function test_dependent_kept_when_required_set_present(): void
    {
        $defs = $this->defs();
        $cfg = $this->configurator($defs);
        // Nothing disabled — luma ships, so its theme is not in replace.
        $sel = new Selection('1.0.0', null, [], [], [], [], [], []);

        $composer = $cfg->build($sel);
        $replace = $composer['replace'] ?? [];
        $this->assertArrayNotHasKey('acme/theme-frontend-luma', $replace);
        $this->assertArrayNotHasKey('acme/module-webapi', $replace);
    }
}
