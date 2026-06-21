<?php

namespace App\Http\Controllers;

use App\Models\SavedConfig;
use App\Services\CatalogRepository;
use App\Services\ComposerJsonRenderer;
use App\Services\Configurator as ConfiguratorService;
use App\Services\Definitions;
use App\Services\DefinitionsSerializer;
use App\Services\InstallTreeResolver;
use App\Services\Selection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Build Canvas configurator. The page is plain Blade + vanilla JS: all
 * interaction (toggling modules, picking a profile, the cross-section ripples)
 * runs client-side in resources/js/maker-engine.js for instant feedback. The
 * server is only hit here to:
 *   - {@see index()}/{@see show()}: render the page with the `window.MAKER` rules
 *     blob and a server-rendered first paint of composer.json + the install tree;
 *   - {@see build()}: regenerate composer.json + the install tree from a posted
 *     {@see Selection} (debounced on the client, behind a spinner);
 *   - {@see save()}: persist a shareable configuration.
 *
 * {@see ConfiguratorService::build()} stays the single source of truth for the
 * actual composer.json — the client engine only drives UI affordances.
 */
class ConfiguratorController extends Controller
{
    public function __construct(
        private readonly Definitions $defs,
        private readonly CatalogRepository $catalog,
        private readonly ConfiguratorService $configurator,
        private readonly ComposerJsonRenderer $renderer,
        private readonly InstallTreeResolver $treeResolver,
        private readonly DefinitionsSerializer $serializer,
    ) {}

    /** Default configurator — latest stable Mage-OS, default selection. */
    public function index(): View
    {
        $sel = Selection::default($this->catalog->latestStable(), $this->defs);

        return $this->page($sel, []);
    }

    /** A saved configuration loaded from its `/c/{id}` share link. */
    public function show(string $id): View
    {
        $cfg = SavedConfig::findOrFail($id);
        $sel = Selection::fromArray(
            array_merge($cfg->selection, ['version' => $cfg->mageos_version]),
            $cfg->mageos_version,
            $this->defs,
        );

        return $this->page($sel, [
            'savedId' => $cfg->id,
            'savedAt' => (string) $cfg->created_at,
            'savedSnapshot' => $this->snapshot($sel),
        ]);
    }

    /**
     * Regenerate the output from a posted Selection. The server re-derives
     * everything from the *raw* selection (disabledSets/profileGroups/…), so a
     * client-engine bug can never produce a wrong build.
     */
    public function build(Request $request): JsonResponse
    {
        $sel = $this->selectionFromRequest($request);

        $composer = $this->configurator->build($sel, (string) $request->input('hyvaProject', ''));
        $tree = $this->treeResolver->resolve($sel);

        return response()->json([
            'composerJson' => $this->renderer->render($composer),
            'installTreeHtml' => $this->treeHtml($tree),
            'treeMeta' => [
                'count' => $tree['count'],
                'byType' => $tree['byType'],
                'missing' => $tree['missing'],
                'fallbackVersion' => $tree['fallbackVersion'],
                'version' => $tree['version'],
            ],
            'packageCount' => $tree['count'],
            'requireCount' => count($composer['require'] ?? []),
            'replaceCount' => count($composer['replace'] ?? []),
            'usesHyva' => ConfiguratorService::requiresHyva($composer),
        ]);
    }

    /** Persist the posted Selection and return its shareable `/c/{id}` link. */
    public function save(Request $request): JsonResponse
    {
        $sel = $this->selectionFromRequest($request);
        $cfg = SavedConfig::create([
            'mageos_version' => $sel->version,
            'selection' => $sel->toArray(),
        ]);

        return response()->json([
            'id' => $cfg->id,
            'url' => url("/c/{$cfg->id}"),
            'starterArg' => url("/c/{$cfg->id}"),
        ]);
    }

    /**
     * Render the page: the `window.MAKER` rules blob + a server first paint of
     * composer.json and the install tree (so the page is correct before the
     * first /api/build fetch).
     *
     * @param  array{savedId?:?string, savedAt?:?string, savedSnapshot?:?string}  $savedMeta
     */
    private function page(Selection $sel, array $savedMeta): View
    {
        $composer = $this->configurator->build($sel);
        $tree = $this->treeResolver->resolve($sel);
        $savedId = $savedMeta['savedId'] ?? null;

        return view('configurator', [
            'maker' => $this->serializer->payload($sel, $savedMeta),
            'composerJson' => $this->renderer->render($composer),
            'tree' => $tree,
            'usesHyva' => ConfiguratorService::requiresHyva($composer),
            'requireCount' => count($composer['require'] ?? []),
            'replaceCount' => count($composer['replace'] ?? []),
            'packageCount' => $tree['count'],
            'savedId' => $savedId,
            'starterArg' => $savedId !== null ? url("/c/{$savedId}") : 'mageos',
        ]);
    }

    /**
     * Reconstruct a Selection from the posted JSON. Only known keys are read
     * (via {@see Selection::fromArray()}); the version is clamped to the catalog
     * and the modulargento distribution is forced off any version that doesn't
     * track it — mirroring the old component's server-authoritative guards.
     */
    private function selectionFromRequest(Request $request): Selection
    {
        $data = (array) $request->input('selection', []);

        $version = is_string($data['version'] ?? null) ? $data['version'] : $this->catalog->latestStable();
        if (! in_array($version, $this->catalog->availableVersions(), true)) {
            abort(422, "Unknown Mage-OS version: {$version}");
        }
        $data['version'] = $version;

        $modulargentoVersions = array_keys((array) config('mageos.modulargento.versions', []));
        if (($data['distribution'] ?? 'standard') === 'modulargento'
            && ! in_array($version, $modulargentoVersions, true)) {
            $data['distribution'] = 'standard';
        }

        return Selection::fromArray($data, $version, $this->defs);
    }

    /** Render the full install-tree pane (type summary, filter, package tree). */
    private function treeHtml(array $tree): string
    {
        return view('partials.install-tree-pane', ['tree' => $tree])->render();
    }

    /**
     * Canonical JSON of a selection (arrays sorted) — the dirty-check baseline
     * the client compares against to know whether the saved `/c/{id}` link is
     * still truthful. Ports the old component's snapshotSelection().
     */
    private function snapshot(Selection $sel): string
    {
        $arr = $sel->toArray();
        $sort = function (&$v) use (&$sort) {
            if (is_array($v)) {
                foreach ($v as &$inner) {
                    $sort($inner);
                }
                if (array_is_list($v)) {
                    sort($v);
                } else {
                    ksort($v);
                }
            }
        };
        $sort($arr);

        return (string) json_encode($arr);
    }
}
