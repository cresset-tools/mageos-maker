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
 * `bougie init --starter <url>` can scaffold a Mage-OS project from a
 * configuration. The consumer side lives in bougie at
 * crates/bougie/src/commands/starter.rs.
 *
 * Manifest shape:
 *   { schema, name, composer-json, services, recipe, notes }
 * Only `schema` + `composer-json` are load-bearing for bougie today; the
 * rest are advisory hints.
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

    private function manifest(Selection $sel): JsonResponse
    {
        // The generated composer.json carries the Hyvä composer-repo URL
        // (when a project slug is configured) but never the license token —
        // that goes in auth.json, surfaced via `notes` below.
        $hyvaProject = (string) (config('mageos.hyva_project') ?? '');
        $composer = $this->configurator->build($sel, $hyvaProject);

        $notes = [];
        if (str_contains((string) json_encode($composer), 'hyva')) {
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
            'notes' => $notes,
        ], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
