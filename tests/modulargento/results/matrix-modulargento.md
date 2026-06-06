# Modulargento removal matrix — profile: `mageos-full`

Module-removal matrix run against **modulargento** (decoupled Mage-OS fork) on bougie services, vs **stock** Mage-OS. A set passes when, after removing it, `composer install` + `setup:install` + `setup:di:compile` all succeed.

**Baseline** (full modulargento overlay, nothing removed): PASS — installs + compiles clean.

**Removable with modulargento: 3 / 13** — newly unlocked vs stock: `msrp`, `newsletter`, `wishlist`.

**Maximal achievable reduction** (every individually-removable set removed together: `msrp`, `newsletter`, `wishlist`): PASS — the reduced-feature install still boots + compiles.

## Per-set: stock vs modulargento

| Set | Stock | Modulargento | Change |
|---|---|---|---|
| `bundle` | ❌ install-failed | ❌ install-failed | same |
| `downloadable` | ❌ install-failed | ❌ install-failed | same |
| `gift-message` | ❌ fail | ❌ fail | same |
| `grouped` | ❌ install-failed | ❌ install-failed | same |
| `instant-purchase` | ❌ fail | ❌ fail | same |
| `media-gallery-sync` | ❌ fail | ❌ fail | same |
| `msrp` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `newsletter` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `product-alert` | ❌ fail | ❌ fail | same |
| `release-notification` | ❌ fail | ❌ fail | same |
| `reviews` | ❌ fail | ❌ fail | same |
| `swatches` | ❌ fail | ❌ fail | same |
| `wishlist` | ❌ fail | ✅ pass | **fail → pass** 🎉 |

## Remaining worklist — still blocked (10 sets)

These need further decoupling in modulargento before they're removable.

### `SQLSTATE[42S02]: Base table or view not found: 1146 Table 'modulargento_mag`

- `bundle`  ([log](raw/bundle.log))

### `Class "Magento\Downloadable\Model\Product\Type" not found`

- `downloadable`  ([log](raw/downloadable.log))

### `Class "Magento\GiftMessage\Helper\Message" does not exist`

- `gift-message`  ([log](raw/gift-message.log))

### `Constant "\Magento\GroupedProduct\Model\Product\Type\Grouped::TYPE_CODE" is not defined`

- `grouped`  ([log](raw/grouped.log))

### `Interface "Magento\InstantPurchase\PaymentMethodIntegration\PaymentTokenFormatterInterface" not found`

- `instant-purchase`  ([log](raw/instant-purchase.log))

### `Class "Magento\MediaGallerySynchronizationApi\Api\SynchronizeFilesInterface  
  " does not exist`

- `media-gallery-sync`  ([log](raw/media-gallery-sync.log))

### `Class "Magento\ProductAlert\Model\StockFactory" does not exist`

- `product-alert`  ([log](raw/product-alert.log))

### `s not exist setup:di:compile`

- `release-notification`  ([log](raw/release-notification.log))

### `Class "Magento\Review\Block\Product\ReviewRenderer" not found`

- `reviews`  ([log](raw/reviews.log))

### `Class "Magento\Swatches\Helper\Media" does not exist`

- `swatches`  ([log](raw/swatches.log))

