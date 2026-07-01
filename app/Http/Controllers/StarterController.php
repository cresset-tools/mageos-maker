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
 *   { schema, name, composer-json, services, recipe, placeholders, auth, notes }
 * Only `schema` + `composer-json` are load-bearing for bougie today; the
 * rest are advisory hints. `placeholders` names literal tokens left in
 * `composer-json` for bougie to fill in interactively; `auth` declares
 * private-repo credentials (a host + username) bougie prompts for and
 * stores as a secret outside the project (see below).
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
     * separate secret declared via the manifest's `auth` block.
     */
    private const HYVA_PROJECT_PLACEHOLDER = '{{hyva_project}}';

    /** The Hyvä themes Composer repo host credentials authenticate to. */
    private const HYVA_REPO_HOST = 'hyva-themes.repo.packagist.com';

    private function manifest(Selection $sel): JsonResponse
    {
        // The generated composer.json references the Hyvä composer-repo URL
        // (only when the selection pulls a hyva-themes/* package) but never
        // a real slug or the license token: the URL carries a placeholder
        // bougie fills in interactively, and the token is requested via the
        // manifest's `auth` block. We deliberately do NOT use this server's
        // configured `mageos.hyva_project` here — that's a build-time secret
        // for catalog lookups, not something to leak into shared starters.
        $composer = $this->configurator->build($sel, self::HYVA_PROJECT_PLACEHOLDER);

        $notes = [];
        $placeholders = [];
        $auth = [];
        if (ConfiguratorService::requiresHyva($composer)) {
            $placeholders[] = [
                'token' => self::HYVA_PROJECT_PLACEHOLDER,
                'prompt' => 'Hyvä project slug',
                'description' => 'Your Hyvä Composer repository slug — the <slug> in '
                    .'https://'.self::HYVA_REPO_HOST.'/<slug>/, found on your '
                    .'hyva.io account dashboard. Needed to download the Hyvä theme packages.',
                'required' => true,
            ];
            // The license token is a secret, so it goes through `auth` (bougie
            // prompts for it and stores it in its own credential store) rather
            // than a placeholder, which would land in the committed
            // composer.json.
            $auth[] = [
                'host' => self::HYVA_REPO_HOST,
                'username' => 'token',
                'prompt' => 'Hyvä license key',
                'description' => 'Your Hyvä license token, found on your hyva.io account '
                    .'dashboard. bougie stores it in its credential store, not in the project.',
                'required' => true,
            ];
            // Fallback hint for users who'd rather set it by hand (or a
            // non-interactive run); bougie prompts for it otherwise.
            $notes[] = 'Hyvä packages need a license token. bougie will prompt for it; '
                .'to set it by hand instead: `composer config --global --auth '
                .'http-basic.'.self::HYVA_REPO_HOST.' token <YOUR_KEY>`.';
        }

        // Add-on post-install steps (CLI commands bougie can't run for the
        // user — module:enable, config generation, asset rebuilds). Emitted
        // when the add-on's package actually lands in the built require map,
        // so we never tell users to configure something they didn't install.
        $require = $composer['require'] ?? [];
        foreach (array_keys($this->defs->addons) as $addonName) {
            $addonNotes = $this->defs->addonNotes($addonName);
            if ($addonNotes === []) {
                continue;
            }
            foreach ($this->defs->addonPackages($addonName) as $pkg) {
                if (isset($require[$pkg])) {
                    $notes = array_merge($notes, $addonNotes);
                    break;
                }
            }
        }

        $name = 'Mage-OS '.$sel->version.($sel->profile ? " ({$sel->profile})" : '');

        return response()->json([
            'schema' => 1,
            'name' => $name,
            'composer-json' => $composer,
            'services' => ['mariadb', 'redis', 'opensearch', 'rabbitmq'],
            'recipe' => 'magento',
            'placeholders' => $placeholders,
            'auth' => $auth,
            'notes' => $notes,
        ], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
