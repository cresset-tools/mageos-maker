<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StarterTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_starter_manifest_has_expected_shape(): void
    {
        $resp = $this->getJson('/starter.json');

        $resp->assertOk();
        $resp->assertJsonPath('schema', 1);
        $resp->assertJsonPath('recipe', 'magento');

        $json = $resp->json();
        $this->assertIsArray($json['composer-json']);
        $this->assertArrayHasKey('require', $json['composer-json']);
        $this->assertContains('mariadb', $json['services']);
        $this->assertArrayHasKey('name', $json);
    }

    public function test_unknown_saved_config_404s(): void
    {
        $this->getJson('/c/00000000-0000-0000-0000-000000000000/starter.json')
            ->assertNotFound();
    }
}
