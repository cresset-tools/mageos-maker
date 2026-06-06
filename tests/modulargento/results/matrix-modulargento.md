# Modulargento removal matrix — profile: `mageos-full`

Module-removal matrix run against **modulargento** (decoupled Mage-OS fork) on bougie services, vs **stock** Mage-OS. A set passes when, after removing it, `composer install` + `setup:install` + `setup:di:compile` all succeed.

**Baseline** (full modulargento overlay, nothing removed): PASS — installs + compiles clean.

**Removable with modulargento: 8 / 14** — newly unlocked vs stock: `instant-purchase`, `media-gallery-sync`, `msrp`, `newsletter`, `product-alert`, `reviews`, `wishlist`.

**Maximal achievable reduction** (every individually-removable set removed together: `_pa-baseline`, `instant-purchase`, `media-gallery-sync`, `msrp`, `newsletter`, `product-alert`, `reviews`, `wishlist`): PASS — the reduced-feature install still boots + compiles.

## Per-set: stock vs modulargento

| Set | Stock | Modulargento | Change |
|---|---|---|---|
| `_pa-baseline` | ❌ — | ✅ pass |  |
| `bundle` | ❌ install-failed | ❌ install-failed | same |
| `downloadable` | ❌ install-failed | ❌ install-failed | same |
| `gift-message` | ❌ fail | ❌ fail | same |
| `grouped` | ❌ install-failed | ❌ install-failed | same |
| `instant-purchase` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `media-gallery-sync` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `msrp` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `newsletter` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `product-alert` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `release-notification` | ❌ fail | ❌ fail | same |
| `reviews` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `swatches` | ❌ fail | ❌ fail | same |
| `wishlist` | ❌ fail | ✅ pass | **fail → pass** 🎉 |

## Remaining worklist — still blocked (6 sets)

These need further decoupling in modulargento before they're removable.

### `SQLSTATE[42S02]: Base table or view not found: 1146 Table 'modulargento_mag`

- `bundle`  ([log](raw/bundle.log))

### `Class "Magento\Downloadable\Model\Product\Type" not found`

- `downloadable`  ([log](raw/downloadable.log))

### `Class "Magento\GiftMessage\Api\ItemRepositoryInterface" does not exist`

- `gift-message`  ([log](raw/gift-message.log))

### `Constant "\Magento\GroupedProduct\Model\Product\Type\Grouped::TYPE_CODE" is not defined`

- `grouped`  ([log](raw/grouped.log))

### `s not exist setup:di:compile`

- `release-notification`  ([log](raw/release-notification.log))

### `Class "Magento\Swatches\Helper\Media" does not exist`

- `swatches`  ([log](raw/swatches.log))

