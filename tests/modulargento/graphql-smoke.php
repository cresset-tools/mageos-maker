<?php
/**
 * Headless GraphQL smoke test for the module-removal matrix.
 *
 * The matrix's install + di:compile gate does NOT exercise the GraphQL schema,
 * which is stitched together from every *GraphQl module's *.graphqls at runtime.
 * Removing a *GraphQl module can leave a dangling schema reference (a staying
 * schema referencing a type/resolver from a removed module) that di:compile never
 * sees but that breaks every GraphQL request.
 *
 * This replicates the core of Magento\GraphQl\Controller\GraphQl::dispatch:
 * load the graphql area, build the full schema (an introspection query forces
 * every registered type to be built — that's where dangling refs throw), and run
 * the query. Prints "GRAPHQL_OK <typeCount>" on success, "GRAPHQL_FAIL <message>"
 * otherwise. Run from a sandbox root: `php .../graphql-smoke.php` (cwd = sandbox).
 */

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Area;

$root = getcwd();
require $root . '/app/bootstrap.php';

try {
    $bootstrap = Bootstrap::create($root, $_SERVER);
    $om = $bootstrap->getObjectManager();

    $om->get(\Magento\Framework\App\State::class)->setAreaCode(Area::AREA_GRAPHQL);
    $configLoader = $om->get(\Magento\Framework\ObjectManager\ConfigLoaderInterface::class);
    $om->configure($configLoader->load(Area::AREA_GRAPHQL));

    // Introspect the whole schema — forces every type/field/resolver class to resolve.
    $query = '{ __schema { queryType { name } mutationType { name } '
        . 'types { name kind } } }';

    $parser = $om->get(\Magento\Framework\GraphQl\Query\QueryParser::class);
    $parsed = $parser->parse($query);

    // Temporal coupling required by the schema generator's field-pruning optimization.
    $om->get(\Magento\Framework\GraphQl\Query\Fields::class)->setQuery($parsed, []);

    // Schema build — dangling type/interface references throw here.
    try {
        $schema = $om->get(\Magento\Framework\GraphQl\Schema\SchemaGeneratorInterface::class)->generate();
    } catch (\Throwable $e) {
        echo 'GRAPHQL_FAIL schema-build ' . get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
        exit(1);
    }

    // Execute introspection. Use webonyx directly with full debug so a wrapped
    // "Internal server error" reveals its underlying cause (e.g. a missing resolver class).
    $result = \GraphQL\GraphQL::executeQuery($schema, $parsed, null, null, []);
    $debug = \GraphQL\Error\DebugFlag::INCLUDE_DEBUG_MESSAGE | \GraphQL\Error\DebugFlag::INCLUDE_TRACE;
    $out = $result->toArray($debug);

    if (!empty($out['errors'])) {
        $msgs = array_map(static function ($e) {
            $m = $e['message'] ?? '';
            if (!empty($e['extensions']['debugMessage'])) {
                $m .= ' :: ' . $e['extensions']['debugMessage'];
            }
            return $m;
        }, $out['errors']);
        echo 'GRAPHQL_FAIL query-errors: ' . implode(' | ', array_slice($msgs, 0, 3)) . PHP_EOL;
        exit(1);
    }
    $result = $out;

    $types = $result['data']['__schema']['types'] ?? [];
    echo 'GRAPHQL_OK types=' . count($types)
        . ' query=' . ($result['data']['__schema']['queryType']['name'] ?? '?')
        . ' mutation=' . ($result['data']['__schema']['mutationType']['name'] ?? '?') . PHP_EOL;
    exit(0);
} catch (\Throwable $e) {
    echo 'GRAPHQL_FAIL ' . get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
