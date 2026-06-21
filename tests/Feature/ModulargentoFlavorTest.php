<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The fully-modular (modulargento) distribution unlocks sets that stock Mage-OS
 * locks on. The configurator UI runs client-side now, so the load-bearing
 * behaviour is exercised through the build endpoint: posting a Selection that
 * disables a normally-locked set actually strips it under modulargento, stays a
 * no-op under standard, and the distribution is clamped off any version that
 * doesn't track the flavor.
 */
class ModulargentoFlavorTest extends TestCase
{
    /** @return list<string> Every Mage-OS version the modulargento flavor is published for. */
    private function mgVersions(): array
    {
        return array_keys((array) config('mageos.modulargento.versions', []));
    }

    private function mgVersion(): string
    {
        return $this->mgVersions()[0] ?? '3.0.0';
    }

    /** @param array<string,mixed> $overrides */
    private function build(array $overrides): array
    {
        $selection = array_merge([
            'version' => $this->mgVersion(),
            'distribution' => 'standard',
            'disabledSets' => [],
            'disabledLayers' => [],
            'enabledLayers' => [],
            'enabledAddons' => [],
            'profileGroups' => ['theme' => 'luma', 'checkout' => 'default'],
        ], $overrides);

        return $this->postJson('/api/build', ['selection' => $selection])
            ->assertOk()
            ->json();
    }

    private function composer(array $resp): array
    {
        return json_decode($resp['composerJson'], true);
    }

    public function test_modulargento_strips_a_normally_locked_set_for_every_published_version(): void
    {
        $versions = $this->mgVersions();
        $this->assertNotEmpty($versions, 'expected at least one configured modulargento version');

        foreach ($versions as $version) {
            $resp = $this->build([
                'version' => $version,
                'distribution' => 'modulargento',
                'disabledSets' => ['gift-message'],
            ]);
            $composer = $this->composer($resp);

            $this->assertArrayHasKey(
                'modulargento/module-gift-message',
                $composer['replace'] ?? [],
                "modulargento $version should strip the locked gift-message set",
            );
            // The per-version template resolves end-to-end: the project pins the
            // matching product edition for the selected version.
            $this->assertSame(
                $version,
                $composer['require']['modulargento/product-community-edition'] ?? null,
                "modulargento $version should pin the matching product edition",
            );
        }
    }

    public function test_standard_keeps_the_locked_set(): void
    {
        $resp = $this->build([
            'distribution' => 'standard',
            'disabledSets' => ['gift-message'],
        ]);
        $replace = $this->composer($resp)['replace'] ?? [];
        $this->assertArrayNotHasKey('mage-os/module-gift-message', $replace);
        $this->assertArrayNotHasKey('modulargento/module-gift-message', $replace);
    }

    public function test_distribution_is_clamped_off_the_tracked_version(): void
    {
        // modulargento requested on a version it doesn't track → forced to
        // standard, so the locked set is kept (not stripped) either way.
        $resp = $this->build([
            'version' => '2.2.2',
            'distribution' => 'modulargento',
            'disabledSets' => ['gift-message'],
        ]);
        $replace = $this->composer($resp)['replace'] ?? [];
        $this->assertArrayNotHasKey('modulargento/module-gift-message', $replace);
        $this->assertArrayNotHasKey('mage-os/module-gift-message', $replace);
    }
}
