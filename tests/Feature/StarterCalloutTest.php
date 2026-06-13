<?php

namespace Tests\Feature;

use App\Models\SavedConfig;
use App\Services\CatalogRepository;
use App\Services\Definitions;
use App\Services\Selection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "try it with bougie" callout in the output dock builds its
 * `bougie new --starter <arg>` command from the shareable `/c/{id}` URL once a
 * config is saved, and the `mageos` alias (default starter) otherwise.
 */
class StarterCalloutTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_page_uses_the_mageos_alias(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('--starter mageos', false);
    }

    public function test_saved_page_uses_the_share_url(): void
    {
        $defs = app(Definitions::class);
        $catalog = app(CatalogRepository::class);
        $sel = Selection::default($catalog->latestStable(), $defs);
        $cfg = SavedConfig::create(['mageos_version' => $sel->version, 'selection' => $sel->toArray()]);

        $this->get("/c/{$cfg->id}")
            ->assertOk()
            ->assertSee(url("/c/{$cfg->id}"), false);
    }
}
