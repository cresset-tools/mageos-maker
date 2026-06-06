# Modulargento removal matrix — profile: `mageos-full`

Module-removal matrix run against **modulargento** (decoupled Mage-OS fork) on bougie services, vs **stock** Mage-OS. A set passes when, after removing it, `composer install` + `setup:install` + `setup:di:compile` all succeed.

**Baseline** (full modulargento overlay, nothing removed): PASS — installs + compiles clean.

**Removable with modulargento: 13 / 14** — newly unlocked vs stock: `downloadable`, `gift-message`, `grouped`, `instant-purchase`, `media-gallery-sync`, `msrp`, `newsletter`, `product-alert`, `release-notification`, `reviews`, `swatches`, `wishlist`.

**Maximal achievable reduction** (every individually-removable set removed together: `downloadable`, `gift-message`, `grouped`, `instant-purchase`, `inventory`, `media-gallery-sync`, `msrp`, `newsletter`, `product-alert`, `release-notification`, `reviews`, `swatches`, `wishlist`): PASS — the reduced-feature install still boots + compiles.

## Per-set: stock vs modulargento

| Set | Stock | Modulargento | Change |
|---|---|---|---|
| `bundle` | ❌ install-failed | ❌ install-failed | same |
| `downloadable` | ❌ install-failed | ✅ pass | **fail → pass** 🎉 |
| `gift-message` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `grouped` | ❌ install-failed | ✅ pass | **fail → pass** 🎉 |
| `instant-purchase` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `inventory` | ✅ pass | ✅ pass | same |
| `media-gallery-sync` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `msrp` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `newsletter` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `product-alert` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `release-notification` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `reviews` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `swatches` | ❌ fail | ✅ pass | **fail → pass** 🎉 |
| `wishlist` | ❌ fail | ✅ pass | **fail → pass** 🎉 |

## Remaining worklist — still blocked (1 sets)

These need further decoupling in modulargento before they're removable.

### `SQLSTATE[42S02]: Base table or view not found: 1146 Table 'modulargento_mag`

- `bundle`  ([log](raw/bundle.log))

