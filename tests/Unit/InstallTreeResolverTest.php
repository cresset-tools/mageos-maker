<?php

namespace Tests\Unit;

use App\Services\AddonVersionResolver;
use App\Services\CatalogRepository;
use App\Services\ComposerRepoIndex;
use App\Services\Configurator;
use App\Services\Definitions;
use App\Services\InstallTreeResolver;
use App\Services\Selection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstallTreeResolverTest extends TestCase
{
    private string $graphsDir = 'graphs-test';

    protected function setUp(): void
    {
        parent::setUp();
        InstallTreeResolver::clearCache();
        Storage::disk('local')->deleteDirectory($this->graphsDir);
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory($this->graphsDir);
        InstallTreeResolver::clearCache();
        parent::tearDown();
    }

    public function test_bfs_walks_reachable_set(): void
    {
        $this->writeBase('1.0.0', [
            'rootRequires' => ['acme/root'],
            'packages' => [
                'acme/root' => ['version' => '1.0.0', 'type' => 'metapackage', 'requires' => ['acme/a', 'acme/b'], 'replaces' => []],
                'acme/a' => ['version' => '1.0.0', 'type' => 'magento2-module', 'requires' => ['acme/c'], 'replaces' => []],
                'acme/b' => ['version' => '1.0.0', 'type' => 'magento2-module', 'requires' => [], 'replaces' => []],
                'acme/c' => ['version' => '1.0.0', 'type' => 'library', 'requires' => [], 'replaces' => []],
            ],
        ]);

        $defs = $this->emptyDefinitions();
        $resolver = new InstallTreeResolver($defs, $this->graphsDir);
        $sel = new Selection('1.0.0', null, [], [], [], [], []);

        $result = $resolver->resolve($sel);

        $this->assertSame(4, $result['count']);
        $this->assertEqualsCanonicalizing(
            ['acme/root', 'acme/a', 'acme/b', 'acme/c'],
            array_column($result['packages'], 'name'),
        );
        $this->assertSame(['library' => 1, 'magento2-module' => 2, 'metapackage' => 1], $result['byType']);
        $this->assertFalse($result['missing']);
    }

    public function test_disabled_set_prunes_subtree(): void
    {
        $this->writeBase('1.0.0', [
            'rootRequires' => ['acme/root'],
            'packages' => [
                'acme/root' => ['version' => '1.0.0', 'type' => 'metapackage', 'requires' => ['acme/wishlist'], 'replaces' => []],
                'acme/wishlist' => ['version' => '1.0.0', 'type' => 'magento2-module', 'requires' => ['acme/wishlist-graphql'], 'replaces' => []],
                'acme/wishlist-graphql' => ['version' => '1.0.0', 'type' => 'magento2-module', 'requires' => [], 'replaces' => []],
            ],
        ]);

        $defs = new Definitions(
            sets: ['wishlist' => ['name' => 'wishlist', 'label' => 'W', 'packages' => ['acme/wishlist']]],
            layers: [], addons: [], profileGroups: [], profiles: [],
        );

        $resolver = new InstallTreeResolver($defs, $this->graphsDir);
        $sel = new Selection('1.0.0', null, ['wishlist'], [], [], [], []);
        $result = $resolver->resolve($sel);

        $names = array_column($result['packages'], 'name');
        $this->assertContains('acme/root', $names);
        $this->assertNotContains('acme/wishlist', $names);
        // wishlist-graphql is only reachable via wishlist; pruning wishlist orphans it.
        $this->assertNotContains('acme/wishlist-graphql', $names);
        $this->assertContains('acme/wishlist', $result['disabledHits']);
    }

    public function test_delta_for_non_default_option_extends_graph(): void
    {
        $this->writeBase('1.0.0', [
            'rootRequires' => ['acme/root'],
            'packages' => [
                'acme/root' => ['version' => '1.0.0', 'type' => 'metapackage', 'requires' => [], 'replaces' => []],
            ],
        ]);
        $this->writeDelta('1.0.0', 'theme', 'hyva', [
            'addRequires' => ['hyva/theme'],
            'addPackages' => [
                'hyva/theme' => ['version' => '1.0.0', 'type' => 'magento2-theme', 'requires' => ['hyva/lib'], 'replaces' => []],
                'hyva/lib' => ['version' => '1.0.0', 'type' => 'library', 'requires' => [], 'replaces' => []],
            ],
        ]);

        $defs = new Definitions(
            sets: [], layers: [], addons: [],
            profileGroups: ['theme' => ['name' => 'theme', 'label' => 'Theme', 'options' => [
                ['name' => 'luma', 'label' => 'Luma', 'default' => true],
                ['name' => 'hyva', 'label' => 'Hyva'],
            ]]],
            profiles: [],
        );
        $resolver = new InstallTreeResolver($defs, $this->graphsDir);
        $sel = new Selection('1.0.0', null, [], [], [], [], ['theme' => 'hyva']);
        $result = $resolver->resolve($sel);
        $names = array_column($result['packages'], 'name');
        $this->assertEqualsCanonicalizing(['acme/root', 'hyva/theme', 'hyva/lib'], $names);
    }

    public function test_missing_graph_falls_back_to_latest(): void
    {
        $this->writeBase('1.0.0', [
            'rootRequires' => ['acme/root'],
            'packages' => ['acme/root' => ['version' => '1.0.0', 'type' => 'metapackage', 'requires' => [], 'replaces' => []]],
        ]);
        $resolver = new InstallTreeResolver($this->emptyDefinitions(), $this->graphsDir);
        $sel = new Selection('9.9.9', null, [], [], [], [], []);
        $r = $resolver->resolve($sel);
        $this->assertTrue($r['missing']);
        $this->assertSame('1.0.0', $r['fallbackVersion']);
        $this->assertSame(1, $r['count']);
    }

    public function test_returns_spanning_tree_with_first_discoverer_as_parent(): void
    {
        // diamond: root -> a, root -> b, a -> shared, b -> shared
        // BFS visits root, then a (enqueues shared), then b (skips shared since seen).
        $this->writeBase('1.0.0', [
            'rootRequires' => ['acme/root'],
            'packages' => [
                'acme/root' => ['version' => '1', 'type' => 'metapackage', 'requires' => ['acme/a', 'acme/b'], 'replaces' => []],
                'acme/a' => ['version' => '1', 'type' => 'library', 'requires' => ['acme/shared'], 'replaces' => []],
                'acme/b' => ['version' => '1', 'type' => 'library', 'requires' => ['acme/shared'], 'replaces' => []],
                'acme/shared' => ['version' => '1', 'type' => 'library', 'requires' => [], 'replaces' => []],
            ],
        ]);

        $resolver = new InstallTreeResolver($this->emptyDefinitions(), $this->graphsDir);
        $tree = $resolver->resolve(new Selection('1.0.0', null, [], [], [], [], []))['tree'];

        $this->assertCount(1, $tree);
        $this->assertSame('acme/root', $tree[0]['name']);
        $childNames = array_column($tree[0]['children'], 'name');
        $this->assertSame(['acme/a', 'acme/b'], $childNames);
        // shared appears under a (first discoverer), not under b.
        $aChildren = array_column($tree[0]['children'][0]['children'], 'name');
        $bChildren = array_column($tree[0]['children'][1]['children'], 'name');
        $this->assertSame(['acme/shared'], $aChildren);
        $this->assertSame([], $bChildren);
        // sharedRefs reflects the diamond: 2 packages require shared.
        $this->assertSame(2, $tree[0]['children'][0]['children'][0]['sharedRefs']);
    }

    public function test_handles_cycles(): void
    {
        $this->writeBase('1.0.0', [
            'rootRequires' => ['a/x'],
            'packages' => [
                'a/x' => ['version' => '1', 'type' => 'library', 'requires' => ['a/y'], 'replaces' => []],
                'a/y' => ['version' => '1', 'type' => 'library', 'requires' => ['a/x'], 'replaces' => []],
            ],
        ]);
        $resolver = new InstallTreeResolver($this->emptyDefinitions(), $this->graphsDir);
        $r = $resolver->resolve(new Selection('1.0.0', null, [], [], [], [], []));
        $this->assertSame(2, $r['count']);
    }

    public function test_performance_under_5ms(): void
    {
        // Synthesize a 500-node graph (chain + fan-out) and time 100 resolves.
        $packages = [];
        $rootRequires = [];
        for ($i = 0; $i < 500; $i++) {
            $deps = [];
            if ($i + 1 < 500) {
                $deps[] = "acme/p$i-next";
            }
            // tiny fan-out to non-existent leaves to grow the edge count realistically
            for ($j = 0; $j < 4 && $i + $j + 1 < 500; $j++) {
                $deps[] = 'acme/p'.($i + $j + 1);
            }
            $packages["acme/p$i"] = ['version' => '1.0.0', 'type' => 'library', 'requires' => $deps, 'replaces' => []];
        }
        $rootRequires[] = 'acme/p0';
        $this->writeBase('perf', ['rootRequires' => $rootRequires, 'packages' => $packages]);

        $resolver = new InstallTreeResolver($this->emptyDefinitions(), $this->graphsDir);
        $sel = new Selection('perf', null, [], [], [], [], []);

        // Warm cache.
        $resolver->resolve($sel);
        InstallTreeResolver::clearCache();

        $start = hrtime(true);
        for ($i = 0; $i < 50; $i++) {
            $resolver->resolve($sel);
        }
        $perIterMicros = ((hrtime(true) - $start) / 50) / 1000;

        $this->assertLessThan(5000, $perIterMicros, "resolve() took $perIterMicros µs/iter, budget is 5000 µs");
    }

    public function test_additive_mode_roots_the_walk_at_the_minimal_base(): void
    {
        // One full-edition graph; the additive walk only swaps the roots.
        $this->writeBase('3.1.0', [
            'rootRequires' => ['acme/full-root'],
            'packages' => [
                'acme/full-root' => ['version' => '3.1.0', 'type' => 'metapackage', 'requires' => ['acme/catalog', 'acme/wishlist', 'acme/paypal'], 'replaces' => []],
                'acme/catalog' => ['version' => '3.1.0', 'type' => 'magento2-module', 'requires' => ['acme/framework'], 'replaces' => []],
                'acme/framework' => ['version' => '3.1.0', 'type' => 'magento2-library', 'requires' => [], 'replaces' => []],
                'acme/wishlist' => ['version' => '3.1.0', 'type' => 'magento2-module', 'requires' => [], 'replaces' => []],
                'acme/paypal' => ['version' => '3.1.0', 'type' => 'magento2-module', 'requires' => ['acme/paypal-graphql'], 'replaces' => []],
                'acme/paypal-graphql' => ['version' => '3.1.0', 'type' => 'magento2-module', 'requires' => [], 'replaces' => []],
                'laminas/laminas-view' => ['version' => '2.36.0', 'type' => 'library', 'requires' => [], 'replaces' => []],
            ],
        ]);

        $defs = new Definitions(
            sets: ['paypal' => ['name' => 'paypal', 'label' => 'PayPal', 'packages' => ['acme/paypal']]],
            layers: [], addons: [], profileGroups: [], profiles: [],
        );
        $catalog = $this->createMock(CatalogRepository::class);
        $catalog->method('packageVersions')->willReturnCallback(fn (string $name) => $name === 'mage-os/product-minimal-edition'
            ? ['3.1.0' => ['require' => ['php' => '~8.4.0', 'acme/catalog' => '3.1.0']]]
            : []);
        $configurator = new Configurator(
            $defs,
            $catalog,
            new AddonVersionResolver($defs, new ComposerRepoIndex([], 'mageos-catalog'), 'mageos-catalog'),
            'https://repo.mage-os.org/',
        );
        $resolver = new InstallTreeResolver($defs, $this->graphsDir, $configurator);

        // Additive, pure minimal + the paypal set: only the minimal roots and
        // the added set are reachable — NOT the full edition (wishlist stays out).
        $additive = $resolver->resolve(new Selection(
            version: '3.1.0', profile: null,
            disabledSets: [], disabledLayers: [], enabledLayers: [], enabledAddons: [], profileGroups: [],
            mode: 'additive', enabledSets: ['paypal'],
        ));
        $names = array_column($additive['packages'], 'name');
        $this->assertEqualsCanonicalizing(
            ['acme/catalog', 'acme/framework', 'acme/paypal', 'acme/paypal-graphql', 'laminas/laminas-view'],
            $names,
        );
        $this->assertNotContains('acme/full-root', $names);
        $this->assertNotContains('acme/wishlist', $names);

        // The same graph resolved subtractively still walks the full edition.
        $subtractive = $resolver->resolve(new Selection('3.1.0', null, [], [], [], [], []));
        $this->assertSame(6, $subtractive['count']);
    }

    private function emptyDefinitions(): Definitions
    {
        return new Definitions([], [], [], [], []);
    }

    private function writeBase(string $version, array $partial): void
    {
        $graph = ['version' => $version] + $partial;
        Storage::disk('local')->put("$this->graphsDir/$version/base.json", json_encode($graph));
    }

    private function writeDelta(string $version, string $group, string $option, array $partial): void
    {
        $delta = ['version' => $version, 'appliesTo' => ['group' => $group, 'option' => $option]] + $partial;
        Storage::disk('local')->put("$this->graphsDir/$version/options/$group/$option.json", json_encode($delta));
    }
}
