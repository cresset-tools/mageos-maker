#!/usr/bin/env php
<?php
// Render the modulargento removal matrix as Markdown, diffed against the stock
// Mage-OS matrix so the wins (fail->pass) and the remaining worklist are obvious.
//
// Usage: render-modulargento.php <matrix-modulargento.json> [<matrix.json (stock)>]

if ($argc < 2) {
    fwrite(STDERR, "usage: render-modulargento.php <matrix-modulargento.json> [<stock matrix.json>]\n");
    exit(2);
}

$mg = json_decode(file_get_contents($argv[1]), true);
if (!is_array($mg)) { fwrite(STDERR, "could not parse {$argv[1]}\n"); exit(2); }

// Stock results keyed by set name, for the comparison column (optional).
$stock = [];
if ($argc >= 3 && is_file($argv[2])) {
    $sd = json_decode(file_get_contents($argv[2]), true);
    foreach ($sd['results'] ?? [] as $r) $stock[$r['set']] = $r['status'];
}

$profile = $mg['profile'] ?? 'mageos-full';
$version = $mg['version'] ?? '';

$rows = [];
$baseline = null; $maxreduce = null;
foreach ($mg['results'] ?? [] as $r) {
    $set = $r['set'] ?? '';
    if ($set === '_baseline')       { $baseline = $r; continue; }
    if ($set === '_max-reduction')  { $maxreduce = $r; continue; }
    $rows[$set] = $r;
}
ksort($rows);

$icon = fn($s) => $s === 'pass' ? '✅' : ($s === 'noop' ? '➖' : '❌');

// Runtime-smoke dimensions (graphql, render) are recorded separately and do NOT
// change a set's install/compile pass/fail — a set can compile yet crash at
// runtime (e.g. a staying block requesting a removed module's price type). Treat
// any value that isn't "ok"/"n/a"/missing as a failure to surface.
$smokeIcon = function ($v) {
    if ($v === null || $v === '' || $v === 'n/a') return '·';
    return $v === 'ok' ? '✅' : '❌';
};
$smokeFailed = fn($v) => !($v === null || $v === '' || $v === 'n/a' || $v === 'ok');
$smokeCell = fn($r) => 'gql ' . $smokeIcon($r['graphql'] ?? 'n/a') . ' · render ' . $smokeIcon($r['render'] ?? 'n/a');

echo "# Modulargento removal matrix — profile: `$profile`";
if ($version !== '') echo " — version: `$version`";
echo "\n\n";
echo "Module-removal matrix run against **modulargento** (decoupled Mage-OS fork) on bougie ";
echo "services, vs **stock** Mage-OS. A set passes when, after removing it, `composer install` + ";
echo "`setup:install` + `setup:di:compile` all succeed.\n\n";

if ($baseline) {
    echo "**Baseline** (full modulargento overlay, nothing removed): " . strtoupper($baseline['status']);
    echo $baseline['status'] === 'pass' ? " — installs + compiles clean.\n\n" : " — see `results/raw/_baseline.log`.\n\n";
}

// Headline counts + wins.
$pass = array_filter($rows, fn($r) => $r['status'] === 'pass');
$wins = [];
foreach ($rows as $set => $r) {
    $was = $stock[$set] ?? null;
    if ($r['status'] === 'pass' && $was !== null && $was !== 'pass' && $was !== 'noop') $wins[$set] = $was;
}
echo "**Removable with modulargento: " . count($pass) . " / " . count($rows) . "**";
if ($wins) echo " — newly unlocked vs stock: " . implode(', ', array_map(fn($s) => "`$s`", array_keys($wins)));
echo ".\n\n";

if ($maxreduce) {
    $passNames = implode(', ', array_map(fn($s) => "`$s`", array_keys($pass)));
    echo "**Maximal achievable reduction** (every individually-removable set removed together"
       . ($passNames !== '' ? ": $passNames" : "") . "): "
       . strtoupper($maxreduce['status'])
       . ($maxreduce['status'] === 'pass' ? " — the reduced-feature install still boots + compiles.\n\n" : " — see log.\n\n");
}

// Runtime-smoke failures — sets that install + compile but crash at runtime
// (storefront price render or admin model-save). These are the gaps install +
// di:compile can't see, so call them out prominently.
$smokeRows = $rows;
if ($baseline) $smokeRows['_baseline'] = $baseline;
if ($maxreduce) $smokeRows['_max-reduction'] = $maxreduce;
$smokeBad = [];
foreach ($smokeRows as $set => $r) {
    foreach (['graphql' => 'GraphQL', 'render' => 'render'] as $dim => $label) {
        if ($smokeFailed($r[$dim] ?? null)) {
            $smokeBad[] = "- `$set` — $label smoke: " . trim((string) $r[$dim]);
        }
    }
}
if ($smokeBad) {
    echo "## ⚠️ Runtime smoke failures (" . count($smokeBad) . ")\n\n";
    echo "These sets pass install + di:compile but **break at runtime** — a staying "
       . "module references something a removed module provided (a price type, factory, "
       . "schema, …). install/compile can't catch these.\n\n";
    echo implode("\n", $smokeBad) . "\n\n";
}

// Per-set comparison table.
echo "## Per-set: stock vs modulargento\n\n";
echo "| Set | Stock | Modulargento | Change | Smoke |\n|---|---|---|---|---|\n";
foreach ($rows as $set => $r) {
    $was = $stock[$set] ?? '—';
    $now = $r['status'];
    $change = '';
    if ($was !== '—' && $was !== 'pass' && $was !== 'noop' && $now === 'pass') $change = '**fail → pass** 🎉';
    elseif ($was === 'pass' && $now !== 'pass')                                $change = '⚠️ regressed';
    elseif ($was === $now)                                                     $change = 'same';
    printf("| `%s` | %s %s | %s %s | %s | %s |\n",
        $set, $icon($was === '—' ? 'x' : $was), $was, $icon($now), $now, $change, $smokeCell($r));
}
echo "\n";

// Remaining worklist: still-failing sets grouped by error fingerprint.
$fails = array_filter($rows, fn($r) => !in_array($r['status'], ['pass','noop'], true));
if ($fails) {
    $groups = [];
    foreach ($fails as $set => $r) {
        $fp = trim($r['fingerprint'] ?? '') ?: 'unclassified';
        $groups[$fp][] = $set;
    }
    uasort($groups, fn($a, $b) => count($b) - count($a));
    echo "## Remaining worklist — still blocked (" . count($fails) . " sets)\n\n";
    echo "These need further decoupling in modulargento before they're removable.\n\n";
    foreach ($groups as $fp => $sets) {
        echo "### `" . $fp . "`\n\n";
        foreach ($sets as $s) echo "- `$s`  ([log](raw/$s.log))\n";
        echo "\n";
    }
}
