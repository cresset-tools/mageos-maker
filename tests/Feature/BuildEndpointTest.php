<?php

namespace Tests\Feature;

use App\Models\SavedConfig;
use App\Services\CatalogRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The configurator's interaction runs client-side; the server is hit only at
 * POST /api/build (regenerate composer.json + install tree from a posted
 * Selection) and POST /save (persist a shareable configuration).
 */
class BuildEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function latest(): string
    {
        return app(CatalogRepository::class)->latestStable();
    }

    private function baseSelection(array $overrides = []): array
    {
        return array_merge([
            'version' => $this->latest(),
            'distribution' => 'standard',
            'disabledSets' => [],
            'disabledLayers' => [],
            'enabledLayers' => [],
            'enabledAddons' => [],
            'profileGroups' => ['theme' => 'luma', 'checkout' => 'default'],
        ], $overrides);
    }

    public function test_build_returns_composer_tree_and_counts(): void
    {
        $this->postJson('/api/build', ['selection' => $this->baseSelection()])
            ->assertOk()
            ->assertJsonStructure([
                'composerJson', 'installTreeHtml',
                'treeMeta' => ['count', 'byType', 'missing'],
                'packageCount', 'requireCount', 'replaceCount', 'usesHyva',
            ])
            ->assertJson(['usesHyva' => false]);
    }

    public function test_unknown_version_is_rejected(): void
    {
        $this->postJson('/api/build', ['selection' => $this->baseSelection(['version' => '9.9.9'])])
            ->assertStatus(422);
    }

    public function test_hyva_addon_flips_uses_hyva(): void
    {
        $this->postJson('/api/build', ['selection' => $this->baseSelection(['enabledAddons' => ['hyva']])])
            ->assertOk()
            ->assertJson(['usesHyva' => true]);
    }

    public function test_save_persists_a_configuration_and_reloads(): void
    {
        $res = $this->postJson('/save', ['selection' => $this->baseSelection()])
            ->assertOk()
            ->assertJsonStructure(['id', 'url', 'starterArg']);

        $id = $res->json('id');
        $this->assertDatabaseHas('saved_configs', ['id' => $id]);
        $this->assertSame(1, SavedConfig::query()->count());

        $this->get("/c/{$id}")->assertOk()->assertSee('build canvas', false);
    }

    public function test_home_page_renders_the_canvas(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('window.MAKER', false)
            ->assertSee('build canvas', false);
    }
}
