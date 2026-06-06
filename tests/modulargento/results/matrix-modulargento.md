# Modulargento removal matrix — profile: `mageos-full`

Module-removal matrix run against **modulargento** (decoupled Mage-OS fork) on bougie services, vs **stock** Mage-OS. A set passes when, after removing it, `composer install` + `setup:install` + `setup:di:compile` all succeed.

**Baseline** (full modulargento overlay, nothing removed): PASS — installs + compiles clean.

**Removable with modulargento: 10 / 13** — newly unlocked vs stock: `gift-message`, `instant-purchase`, `media-gallery-sync`, `msrp`, `newsletter`, `product-alert`, `release-notification`, `reviews`, `swatches`, `wishlist`.

**Maximal achievable reduction** (every individually-removable set removed together: `gift-message`, `instant-purchase`, `media-gallery-sync`, `msrp`, `newsletter`, `product-alert`, `release-notification`, `reviews`, `swatches`, `wishlist`): PASS — the reduced-feature install still boots + compiles.

## Per-set: stock vs modulargento

| Set | Stock | Modulargento | Change |
|---|---|---|---|
| `bundle` | ❌ install-failed | ❌ install-failed | same |
| `downloadable` | ❌ install-failed | ❌ install-failed | same |
| `gift-message` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `grouped` | ❌ install-failed | ❌ install-failed | same |
| `instant-purchase` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `media-gallery-sync` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `msrp` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `newsletter` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `product-alert` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `release-notification` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `reviews` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `swatches` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `wishlist` | ❌ fail | ✅ pass | **fail → pass** 🎉 |

## Remaining worklist — still blocked (3 sets)

These need further decoupling in modulargento before they're removable.

### `SQLSTATE[42S02]: Base table or view not found: 1146 Table 'modulargento_mag`

- `bundle`  ([log](raw/bundle.log))

### `Class "Magento\Downloadable\Model\Product\Type" not found`

- `downloadable`  ([log](raw/downloadable.log))

### `Constant "\Magento\GroupedProduct\Model\Product\Type\Grouped::TYPE_CODE" is not defined`

- `grouped`  ([log](raw/grouped.log))

