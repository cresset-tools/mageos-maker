# Modulargento removal matrix — profile: `mageos-full`

Module-removal matrix run against **modulargento** (decoupled Mage-OS fork) on bougie services, vs **stock** Mage-OS. A set passes when, after removing it, `composer install` + `setup:install` + `setup:di:compile` all succeed.

**Baseline** (full modulargento overlay, nothing removed): PASS — installs + compiles clean.

**Removable with modulargento: 15 / 15** — newly unlocked vs stock: `bundle`, `downloadable`, `gift-message`, `grouped`, `instant-purchase`, `media-gallery-sync`, `msrp`, `newsletter`, `product-alert`, `release-notification`, `reviews`, `swatches`, `wishlist`.

**Maximal achievable reduction** (every individually-removable set removed together: `bundle`, `downloadable`, `gift-message`, `grouped`, `instant-purchase`, `inventory`, `media-gallery`, `media-gallery-sync`, `msrp`, `newsletter`, `product-alert`, `release-notification`, `reviews`, `swatches`, `wishlist`): PASS — the reduced-feature install still boots + compiles.

## Per-set: stock vs modulargento

| Set | Stock | Modulargento | Change | Smoke |
|---|---|---|---|---|
| `bundle` | ❌ install-failed | ✅ pass | **fail → pass** 🎉 | gql ✅ · render · |
| `downloadable` | ❌ install-failed | ✅ pass | **fail → pass** 🎉 | gql ✅ · render · |
| `gift-message` | ❌ fail | ✅ pass | **fail → pass** 🎉 | gql ✅ · render · |
| `grouped` | ❌ install-failed | ✅ pass | **fail → pass** 🎉 | gql ✅ · render · |
| `instant-purchase` | ❌ fail | ✅ pass | **fail → pass** 🎉 | gql ✅ · render · |
| `inventory` | ✅ pass | ✅ pass | same | gql ✅ · render · |
| `media-gallery` | ❌ — | ✅ pass |  | gql ✅ · render ✅ |
| `media-gallery-sync` | ❌ fail | ✅ pass | **fail → pass** 🎉 | gql ✅ · render ✅ |
| `msrp` | ❌ fail | ✅ pass | **fail → pass** 🎉 | gql ✅ · render · |
| `newsletter` | ❌ fail | ✅ pass | **fail → pass** 🎉 | gql ✅ · render · |
| `product-alert` | ❌ fail | ✅ pass | **fail → pass** 🎉 | gql ✅ · render · |
| `release-notification` | ❌ fail | ✅ pass | **fail → pass** 🎉 | gql ✅ · render · |
| `reviews` | ❌ fail | ✅ pass | **fail → pass** 🎉 | gql ✅ · render · |
| `swatches` | ❌ fail | ✅ pass | **fail → pass** 🎉 | gql ✅ · render · |
| `wishlist` | ❌ fail | ✅ pass | **fail → pass** 🎉 | gql ✅ · render · |

