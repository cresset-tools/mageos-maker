<?php

namespace Tests\Feature;

use App\Models\SavedConfig;
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

        // The license-key auth prompt rides the same gate: present iff a
        // hyva-themes/* package is required, so no-Hyvä starters don't ask
        // for a token the user doesn't need.
        $hasAuth = (bool) array_filter(
            $json['auth'] ?? [],
            static fn ($a): bool => is_array($a) && str_contains($a['host'] ?? '', 'hyva-themes.repo.packagist.com'),
        );
        $this->assertSame($requiresHyva, $hasAuth, 'Hyvä auth prompt must appear iff a hyva-themes/* package is required');
    }

    /**
     * A shared starter must carry a placeholder in the Hyvä repo URL — never
     * this server's configured `mageos.hyva_project` slug, which is account-
     * identifying and would be inherited by every consumer of the link. bougie
     * fills the placeholder in interactively (see crates/bougie/src/commands/
     * starter.rs).
     */
    public function test_hyva_repo_url_carries_a_placeholder_not_the_server_slug(): void
    {
        config(['mageos.hyva_project' => 'server-private-slug']);

        $cfg = SavedConfig::create([
            'mageos_version' => '3.0.0',
            'selection' => [
                'profileGroups' => ['theme' => 'hyva'],
                'enabledAddons' => ['hyva'],
            ],
        ]);

        $json = $this->getJson("/c/{$cfg->id}/starter.json")->assertOk()->json();

        $require = $json['composer-json']['require'] ?? [];
        $requiresHyva = (bool) array_filter(
            array_keys($require),
            static fn ($pkg): bool => str_starts_with((string) $pkg, 'hyva-themes/'),
        );
        $this->assertTrue($requiresHyva, 'fixture selection must pull a hyva-themes/* package');

        $hyvaRepo = collect($json['composer-json']['repositories'] ?? [])
            ->first(static fn ($r): bool => is_array($r) && str_contains($r['url'] ?? '', 'hyva-themes.repo.packagist.com'));
        $this->assertNotNull($hyvaRepo, 'Hyvä repo must be present');

        // The leak we are fixing: the server's own slug must not appear.
        $this->assertStringNotContainsString('server-private-slug', $hyvaRepo['url']);
        $this->assertStringContainsString('{{hyva_project}}', $hyvaRepo['url']);

        // ...and the placeholder must be declared so bougie knows to prompt.
        $placeholder = collect($json['placeholders'] ?? [])
            ->first(static fn ($p): bool => is_array($p) && ($p['token'] ?? null) === '{{hyva_project}}');
        $this->assertNotNull($placeholder, 'a placeholder entry must declare the Hyvä token');
        $this->assertTrue($placeholder['required'] ?? false);
        $this->assertNotEmpty($placeholder['prompt'] ?? '');
        // The description tells the user what they're entering.
        $this->assertNotEmpty($placeholder['description'] ?? '');

        // The license token is a secret, so it must be declared via `auth`
        // (bougie prompts and stores it outside the project) — never as a
        // placeholder that would land in the committed composer.json.
        $auth = collect($json['auth'] ?? [])
            ->first(static fn ($a): bool => is_array($a) && ($a['host'] ?? null) === 'hyva-themes.repo.packagist.com');
        $this->assertNotNull($auth, 'an auth entry must declare the Hyvä repo host');
        $this->assertSame('token', $auth['username'] ?? null);
        $this->assertTrue($auth['required'] ?? false);
        $this->assertNotEmpty($auth['prompt'] ?? '');
        $this->assertNotEmpty($auth['description'] ?? '');
    }

    public function test_default_starter_has_no_placeholders(): void
    {
        $json = $this->getJson('/starter.json')->assertOk()->json();

        $this->assertArrayHasKey('placeholders', $json);
        $this->assertSame([], $json['placeholders']);
    }

    /**
     * An add-on's post-install `notes` (CLI steps bougie can't run for the
     * user) ride into the manifest's `notes` when — and only when — the
     * add-on's package actually lands in the built require map. The Hyvä Loki
     * Checkout variant carries module:enable / hyva:config:generate / Tailwind
     * rebuild steps; a default starter that pulls neither must not surface them.
     */
    public function test_loki_checkout_post_install_notes_appear_only_when_selected(): void
    {
        // Default starter doesn't pull Loki Checkout — none of its notes show.
        $default = $this->getJson('/starter.json')->assertOk()->json();
        $this->assertEmpty(array_filter(
            $default['notes'] ?? [],
            static fn (string $n): bool => str_contains($n, 'Loki Checkout'),
        ), 'default starter must not carry Loki Checkout notes');

        // A Hyvä theme + Loki Checkout selection forces the hyva variant, which
        // pulls loki-checkout/magento2-hyva and thus its post-install notes.
        $cfg = SavedConfig::create([
            'mageos_version' => '3.0.0',
            'selection' => [
                'profileGroups' => ['theme' => 'hyva', 'checkout' => 'loki-checkout'],
                'enabledAddons' => ['hyva'],
            ],
        ]);

        $json = $this->getJson("/c/{$cfg->id}/starter.json")->assertOk()->json();

        $this->assertArrayHasKey('loki-checkout/magento2-hyva', $json['composer-json']['require'] ?? []);

        $notes = $json['notes'] ?? [];
        $this->assertNotEmpty(array_filter(
            $notes,
            static fn (string $n): bool => str_contains($n, 'hyva:config:generate'),
        ), 'Loki Checkout (Hyvä) post-install notes must appear when its package is required');
    }
}
