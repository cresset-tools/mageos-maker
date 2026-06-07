<?php
/**
 * Headless render smoke test for the module-removal matrix.
 *
 * The matrix's install + di:compile + graphql gates never exercise storefront or
 * admin *render/dispatch* paths, so a staying module can keep a runtime
 * reference to something only a removed module provides — and still compile.
 * Two classes of bug this has caught:
 *
 *   - Storefront price render: Catalog's FinalPriceBox / tier_prices.phtml ask
 *     the price pool for 'msrp_price' (registered only by Magento_Msrp). With
 *     Msrp removed the pool miss reaches the price factory as an empty class →
 *     "ReflectionException: Class \"\" does not exist".
 *   - Admin model-save observers: admin-activity-log's model_save_before observer
 *     builds a di.xml factory map that eagerly instantiates a removed module's
 *     factory (e.g. Newsletter\Model\SubscriberFactory) → fatal on any admin save.
 *
 * Area code can only be set once per process, so this script takes the area as
 * its first argument; one-shot.sh runs it once per area. Prints
 * "RENDER_OK <area> ..." on success, "RENDER_FAIL <area> ..." otherwise.
 * Run from a sandbox root: `php .../render-smoke.php <frontend|adminhtml>`.
 */

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Area;

$area = $argv[1] ?? 'frontend';
$root = getcwd();
require $root . '/app/bootstrap.php';

try {
    $bootstrap = Bootstrap::create($root, $_SERVER);
    $om = $bootstrap->getObjectManager();
    $om->get(\Magento\Framework\App\State::class)->setAreaCode($area);
    $configLoader = $om->get(\Magento\Framework\ObjectManager\ConfigLoaderInterface::class);
    $om->configure($configLoader->load($area));
} catch (\Throwable $e) {
    echo "RENDER_FAIL $area bootstrap " . get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

if ($area === Area::AREA_ADMINHTML) {
    exit(adminObserverSmoke($om, $area));
}

exit(frontendPriceSmoke($om, $area));

/**
 * Instantiate every observer registered for the global model-lifecycle events.
 * Building an observer resolves its full constructor tree, so a dangling
 * dependency on a removed module (e.g. an eager di.xml factory map) fatals here.
 */
function adminObserverSmoke($om, string $area): int
{
    $events = [
        'model_save_before', 'model_save_after',
        'model_delete_before', 'model_delete_after',
        'model_load_after',
    ];
    try {
        $eventConfig = $om->get(\Magento\Framework\Event\ConfigInterface::class);
    } catch (\Throwable $e) {
        echo "RENDER_FAIL $area observer-config " . get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
        return 1;
    }

    $seen = [];
    $count = 0;
    foreach ($events as $event) {
        foreach ($eventConfig->getObservers($event) as $observer) {
            $class = is_array($observer) ? ($observer['instance'] ?? null) : null;
            if (!$class || isset($seen[$class])) {
                continue;
            }
            $seen[$class] = true;
            $count++;
            try {
                $om->get($class);
            } catch (\Throwable $e) {
                echo "RENDER_FAIL $area observer $event $class :: "
                    . get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
                return 1;
            }
        }
    }

    echo "RENDER_OK $area observers=$count" . PHP_EOL;
    return 0;
}

/**
 * Render a synthetic simple product's price boxes (final_price + tier_price).
 * That drives Catalog's price-render blocks/templates, which is where a staying
 * block asking the price pool for a removed module's price type blows up.
 */
function frontendPriceSmoke($om, string $area): int
{
    // Setup: a persisted simple product. A real entity is needed because the
    // price render's cache-identity plugins (e.g. ConfigurableProduct's
    // super_link lookup) query the DB by product id. Setup failures are reported
    // distinctly so they aren't mistaken for a decoupling regression.
    try {
        $repo = $om->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
        try {
            $product = $repo->get('render-smoke');
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $product = $om->create(\Magento\Catalog\Model\Product::class);
            $product->setSku('render-smoke')
                ->setName('Render Smoke')
                ->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
                ->setAttributeSetId(4)
                ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
                ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
                ->setPrice(9.99)
                ->setWebsiteIds([1])
                ->setStockData(['use_config_manage_stock' => 1, 'qty' => 100, 'is_in_stock' => 1]);
            $product = $repo->save($product);
        }
    } catch (\Throwable $e) {
        echo "RENDER_FAIL $area price-smoke-setup " . get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
        return 1;
    }

    try {
        // The product.price.render.default block (Magento\Framework\Pricing\Render)
        // self-loads its price-type → render-block/template map from the
        // 'catalog_product_prices' layout handle on render(); create it directly
        // rather than generating the whole page layout.
        $layout = $om->get(\Magento\Framework\View\LayoutInterface::class);
        $priceRender = $layout->createBlock(
            \Magento\Framework\Pricing\Render::class,
            'product.price.render.default',
            ['data' => [
                'price_render_handle' => 'catalog_product_prices',
                'use_link_for_as_low_as' => true,
            ]]
        );

        $rendered = [];
        foreach (['final_price', 'tier_price'] as $priceCode) {
            $html = $priceRender->render($priceCode, $product, ['zone' => 'item_view']);
            $rendered[] = $priceCode . '(' . strlen((string) $html) . 'b)';
        }
    } catch (\Throwable $e) {
        echo "RENDER_FAIL $area price-render " . get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
        return 1;
    }

    echo "RENDER_OK $area " . implode(' ', $rendered) . PHP_EOL;
    return 0;
}
