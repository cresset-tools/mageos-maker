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

    /**
     * The Hyvä auth-token note — and the licensed Hyvä composer repo — must
     * appear if and only if the selection actually requires a hyva-themes/*
     * package. Previously the note fired whenever a project slug was
     * configured (it matched the repo URL), so default no-Hyvä starters told
     * users to set up a token they didn't need.
     */
    public function test_hyva_note_and_repo_appear_only_when_a_hyva_package_is_required(): void
    {
        $json = $this->getJson('/starter.json')->assertOk()->json();

        $require = $json['composer-json']['require'] ?? [];
        $requiresHyva = (bool) array_filter(
            array_keys($require),
            static fn ($pkg): bool => str_starts_with((string) $pkg, 'hyva-themes/'),
        );

        $hasNote = (bool) array_filter(
            $json['notes'] ?? [],
            static fn (string $n): bool => str_contains($n, 'Hyvä'),
        );
        $this->assertSame($requiresHyva, $hasNote, 'Hyvä note must appear iff a hyva-themes/* package is required');

        $hasRepo = (bool) array_filter(
            $json['composer-json']['repositories'] ?? [],
            static fn ($r): bool => is_array($r) && str_contains($r['url'] ?? '', 'hyva-themes.repo.packagist.com'),
        );
        $this->assertSame($requiresHyva, $hasRepo, 'Hyvä repo must appear iff a hyva-themes/* package is required');
    }
}
