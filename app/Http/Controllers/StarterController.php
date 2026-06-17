<?php

namespace App\Http\Controllers;

use App\Models\SavedConfig;
use App\Services\CatalogRepository;
use App\Services\Configurator as ConfiguratorService;
use App\Services\Definitions;
use App\Services\Selection;
use Illuminate\Http\JsonResponse;

/**
 * Serves the bougie "starter-pack" manifest (schema 1) so
 * `bougie new --starter <url>` can scaffold a Mage-OS project from a
 * configuration. The consumer side lives in bougie at
 * crates/bougie/src/commands/starter.rs.
 *
 * Manifest shape:
 *   { schema, name, composer-json, services, recipe, placeholders, notes }
 * Only `schema` + `composer-json` are load-bearing for bougie today; the
 * rest are advisory hints. `placeholders` names literal tokens left in
 * `composer-json` for bougie to fill in interactively (see below).
 */
class StarterController extends Controller
{
    public function __construct(
        private readonly Definitions $defs,
        private readonly CatalogRepository $catalog,
        private readonly ConfiguratorService $configurator,
    ) {}

    /** Default starter — latest stable Mage-OS, default selection. */
    public function defaultStarter(): JsonResponse
    {
        $sel = Selection::default($this->catalog->latestStable(), $this->defs);

        return $this->manifest($sel);
    }

    /** Starter for a saved configuration (the /c/{id} share link). */
    public function show(string $id): JsonResponse
    {
        $cfg = SavedConfig::findOrFail($id);
        $sel = Selection::fromArray(
            array_merge($cfg->selection, ['version' => $cfg->mageos_version]),
            $cfg->mageos_version,
            $this->defs,
        );

        return $this->manifest($sel);
    }

    /**
     * Literal token left in the generated composer.json's Hyvä repo URL.
     * A *shared* starter must never carry this server's own Hyvä project
     * slug (it's account-identifying, and every consumer would inherit it),
     * so the repo URL gets this placeholder and bougie prompts the user for
     * their own slug at `bougie new --starter` time. The license token is a
     * separate secret that lives in auth.json (see the note below).
     */
    private const HYVA_PROJECT_PLACEHOLDER = '{{hyva_project}}';

    private function manifest(Selection $sel): JsonResponse
    {
        // The generated composer.json references the Hyvä composer-repo URL
        // (only when the selection pulls a hyva-themes/* package) but never
        // a real slug or the license token: the URL carries a placeholder
        // bougie fills in interactively, and the token goes in auth.json
        // (surfaced via `notes`). We deliberately do NOT use this server's
        // configured `mageos.hyva_project` here — that's a build-time secret
        // for catalog lookups, not something to leak into shared starters.
        $composer = $this->configurator->build($sel, self::HYVA_PROJECT_PLACEHOLDER);

        $notes = [];
        $placeholders = [];
        if (ConfiguratorService::requiresHyva($composer)) {
            $placeholders[] = [
                'token' => self::HYVA_PROJECT_PLACEHOLDER,
                'prompt' => 'Hyvä project slug',
                'description' => 'Your Hyvä Composer repository slug — the <slug> in '
                    .'https://hyva-themes.repo.packagist.com/<slug>/, found on your '
                    .'hyva.io account dashboard. Needed to download the Hyvä theme packages.',
                'required' => true,
            ];
            $notes[] = 'Hyvä packages need a license token in auth.json: '
                .'`composer config --global --auth '
                .'http-basic.hyva-themes.repo.packagist.com token <YOUR_KEY>`.';
        }

        $name = 'Mage-OS '.$sel->version.($sel->profile ? " ({$sel->profile})" : '');

        return response()->json([
            'schema' => 1,
            'name' => $name,
            'composer-json' => $composer,
            'services' => ['mariadb', 'redis', 'opensearch', 'rabbitmq'],
            'recipe' => 'magento',
            'placeholders' => $placeholders,
            'notes' => $notes,
        ], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
