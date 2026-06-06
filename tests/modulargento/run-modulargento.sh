#!/usr/bin/env bash
# Drive the per-set removal matrix against MODULARGENTO sources (the decoupled
# Mage-OS fork) instead of stock Mage-OS, on this box's bougie services.
#
# Modulargento breaks the hard cross-module deps that make a handful of stock
# sets non-removable. This runner overlays its decoupled module sources (via the
# one-shot `--app-code` hook) on top of a normal stock install, then disables
# each target set and records whether install + di:compile still pass. The
# headline: newsletter / reviews / wishlist flip fail->pass; the rest stay red
# as the remaining decoupling worklist.
#
# Run it INSIDE the modulargento bougie env so php (8.4 + Magento extensions),
# composer, and the mysql socket shims are on PATH and the BOUGIE_SERVICE_* conn
# vars are injected:
#
#   cd ~/modulargento-magento2 && bougie run -- \
#     ~/mageos-maker/tests/modulargento/run-modulargento.sh [flags]
#
# Usage:
#   run-modulargento.sh [--version VER] [--profile NAME]
#                       [--modulargento PATH] [--only s1,s2] [--skip s1,s2]
#
# Defaults: profile=mageos-full, modulargento=~/modulargento-magento2, targets =
# the sets marked `removable: false` in definitions/sets/, plus a combined
# "maximal reduction" row (newsletter+reviews+wishlist removed together).

set -u
set -o pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$script_dir/../.." && pwd)"
results_dir="$script_dir/results"
per_set_dir="$results_dir/per-set-modulargento"
sandboxes_dir="$script_dir/sandboxes"

PROFILE="mageos-full"
VERSION=""
ONLY=""
SKIP=""
MODULARGENTO_SRC="${MODULARGENTO_SRC:-$HOME/modulargento-magento2}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --version)      VERSION="$2"; shift 2 ;;
    --profile)      PROFILE="$2"; shift 2 ;;
    --modulargento) MODULARGENTO_SRC="$2"; shift 2 ;;
    --only)         ONLY="$2"; shift 2 ;;
    --skip)         SKIP="$2"; shift 2 ;;
    -h|--help)      sed -n '2,30p' "$0"; exit 0 ;;
    *) echo "unknown flag: $1" >&2; exit 2 ;;
  esac
done

app_code_src="$MODULARGENTO_SRC/app/code/Magento"
if [[ ! -d "$app_code_src" ]]; then
  echo "modulargento app/code not found: $app_code_src" >&2; exit 1
fi

# setup/src performance-fixture files decoupled from optional modules. These live
# outside app/code so --app-code can't reach them; overlay them on every row via
# one-shot's --setup-overlay (they're universally beneficial and don't depend on
# which set is removed).
SETUP_OVERLAY_FILES="setup/src/Magento/Setup/Fixtures/AttributeSet/SwatchesGenerator.php,setup/src/Magento/Setup/Fixtures/EavVariationsFixture.php"

# --- bougie service wiring (read from injected BOUGIE_SERVICE_* env) ----------
: "${BOUGIE_SERVICE_MARIADB_DATABASE:?run inside 'bougie run' — BOUGIE_SERVICE_MARIADB_* not set}"
export MODULARGENTO=1
export MODULARGENTO_DB="$BOUGIE_SERVICE_MARIADB_DATABASE"
export DB_HOST="localhost"   # PDO reaches the socket via pdo_mysql.default_socket
export DB_USER="$BOUGIE_SERVICE_MARIADB_USER"
export DB_PASSWORD="$BOUGIE_SERVICE_MARIADB_PASSWORD"
export OPENSEARCH_HOST="${BOUGIE_SERVICE_OPENSEARCH_HOST:-127.0.0.1}"
export OPENSEARCH_PORT="${BOUGIE_SERVICE_OPENSEARCH_PORT:-9200}"
export PROFILE
[[ -n "$VERSION" ]] && export MAGEOS_VERSION="$VERSION"
export COMPOSER_CACHE_DIR="${COMPOSER_CACHE_DIR:-$HOME/.cache/composer}"

mkdir -p "$results_dir" "$per_set_dir" "$sandboxes_dir"

# --- overlay computation ------------------------------------------------------
# Decoupled app/code modules whose CODE changed in modulargento — overlay these
# to get the decoupled behaviour. (GraphQl is intentionally omitted: its only
# diff is composer.json metadata requiring the extracted framework-graph-ql
# sub-packages; no code change, and the framework GraphQl classes still come
# from stock vendor magento/framework, so overlaying it has no runtime effect.)
DECOUPLED_MODULES=(AdminAnalytics Bundle Catalog CatalogImportExport CatalogWidget Checkout Customer CustomerGraphQl GiftMessage GiftMessageGraphQl GroupedProduct MediaGalleryApi MediaGalleryCatalogIntegration MediaGalleryIntegration MediaGallerySynchronization MediaGalleryUi Msrp Newsletter Paypal PaypalInstantPurchase ProductAlert QuoteGraphQl ReleaseNotification Reports Review Sales Weee Wishlist)
# Bridge modules added by modulargento — restore reporting/glue that the decoupling
# stripped out of staying modules. Each needs the feature(s) it bridges present:
# Review/Wishlist reporting, and the Weee<->Swatches listing glue (WeeeSwatches).
BRIDGE_MODULES=(ReviewReports WishlistReports WeeeSwatches)

# Echo the CSV of modules to overlay for a given set of disabled sets. A removed
# feature drops its own decoupled module and any bridge that requires it; only
# newsletter/reviews/wishlist overlap the decoupled/bridge set.
overlay_for_disabled() {
  local -A excl=()
  local s
  for s in "$@"; do
    case "$s" in
      wishlist)   excl[Wishlist]=1; excl[WishlistReports]=1 ;;
      reviews)    excl[Review]=1;   excl[ReviewReports]=1 ;;
      newsletter) excl[Newsletter]=1 ;;
      msrp)       excl[Msrp]=1 ;;
      grouped)    excl[GroupedProduct]=1 ;;
      instant-purchase) excl[PaypalInstantPurchase]=1 ;;
      media-gallery-sync) excl[MediaGallerySynchronization]=1 ;;
      product-alert) excl[ProductAlert]=1 ;;
      gift-message) excl[GiftMessage]=1; excl[GiftMessageGraphQl]=1 ;;
      release-notification) excl[ReleaseNotification]=1 ;;
      swatches) excl[WeeeSwatches]=1 ;;
      weee) excl[WeeeSwatches]=1 ;;
    esac
  done
  local out=() m
  for m in "${DECOUPLED_MODULES[@]}" "${BRIDGE_MODULES[@]}"; do
    [[ -z "${excl[$m]:-}" ]] && out+=("$m")
  done
  (IFS=,; echo "${out[*]}")
}

# Patched vendor add-on forks (cresset-tools) that decouple themselves from a
# core module being removed. page-builder-widget + admin-activity-log are
# decoupled from Review and stay installed regardless, so overlay them always;
# inventory-product-alert is an MSI module — removed with product-alert AND with the
# whole MSI (inventory) set — so only overlay it when neither is being removed
# (otherwise it shouldn't be present and would leave a dangling vendor dir).
VENDOR_FORKS="${VENDOR_FORKS:-$HOME/vendor-forks}"
vendor_overlay_args() {
  local disabled_csv=",${1},"
  local -a a=()
  [[ -d "$VENDOR_FORKS/module-page-builder-widget" ]] && a+=(--vendor-overlay "$VENDOR_FORKS/module-page-builder-widget:vendor/mage-os/module-page-builder-widget")
  [[ -d "$VENDOR_FORKS/module-admin-activity-log" ]] && a+=(--vendor-overlay "$VENDOR_FORKS/module-admin-activity-log:vendor/mage-os/module-admin-activity-log")
  if [[ "$disabled_csv" != *",product-alert,"* && "$disabled_csv" != *",inventory,"* \
        && -d "$VENDOR_FORKS/module-inventory-product-alert" ]]; then
    a+=(--vendor-overlay "$VENDOR_FORKS/module-inventory-product-alert:vendor/mage-os/module-inventory-product-alert")
  fi
  printf '%s\n' "${a[@]}"
}

# Run one row through one-shot with the right overlay. $1=set-name, $2=extra-disable-csv
run_row() {
  local name="$1" extra="${2:-}"
  local disabled=()
  [[ "$name" != _* ]] && disabled+=("$name")
  [[ -n "$extra" ]] && IFS=',' read -ra ed <<< "$extra" && disabled+=("${ed[@]}")
  local overlay; overlay="$(overlay_for_disabled "${disabled[@]+"${disabled[@]}"}")"
  local disabled_csv; disabled_csv="$(IFS=,; echo "${disabled[*]+"${disabled[*]}"}")"
  mapfile -t vargs < <(vendor_overlay_args "$disabled_csv")
  EXTRA_DISABLE="$extra" "$script_dir/one-shot.sh" "$name" ${VERSION:+"$VERSION"} \
      --app-code "$app_code_src:$overlay" \
      --setup-overlay "$MODULARGENTO_SRC:$SETUP_OVERLAY_FILES" \
      "${vargs[@]+"${vargs[@]}"}" > "$per_set_dir/$name.json" 2>/dev/null || true
  python3 -c 'import json,sys; print(json.load(open(sys.argv[1]))["status"])' "$per_set_dir/$name.json" 2>/dev/null || echo unknown
}

# --- baseline (full modulargento overlay, nothing disabled) -------------------
echo "[mg] modulargento src: $MODULARGENTO_SRC"
echo "[mg] db=$MODULARGENTO_DB user=$DB_USER opensearch=$OPENSEARCH_HOST:$OPENSEARCH_PORT"
echo "[mg] generating baseline composer.json ($PROFILE) for noop detection"
baseline_dir="$sandboxes_dir/_baseline"; mkdir -p "$baseline_dir"
configure_args=(--profile="$PROFILE" --output="$baseline_dir/composer.json")
[[ -n "$VERSION" ]] && configure_args+=(--mageos-version="$VERSION")
( cd "$PROJECT_ROOT" && php artisan mageos:configure "${configure_args[@]}" )
export BASELINE_COMPOSER="$baseline_dir/composer.json"

echo "[mg] running baseline (full overlay + install + di:compile)"
bstatus="$(run_row _baseline)"
echo "[mg] baseline status: $bstatus"
if [[ "$bstatus" != "pass" ]]; then
  echo "[mg] baseline did not pass — aborting. See $results_dir/raw/_baseline.log" >&2
  exit 1
fi

# --- target sets: removable:false by default, filtered by --only/--skip -------
mapfile -t all_sets < <(cd "$PROJECT_ROOT/definitions/sets" && grep -l 'removable: false' *.yaml | sed 's/\.yaml$//' | sort)

csv_to_lines() { local IFS=','; read -ra a <<< "$1"; for x in "${a[@]}"; do x="${x// /}"; [[ -n "$x" ]] && echo "$x"; done; }
if [[ -n "$ONLY" ]]; then
  declare -A keep=(); while read -r k; do keep[$k]=1; done < <(csv_to_lines "$ONLY")
  f=(); for s in "${all_sets[@]}"; do [[ -n "${keep[$s]:-}" ]] && f+=("$s"); done; all_sets=("${f[@]}")
fi
if [[ -n "$SKIP" ]]; then
  declare -A drop=(); while read -r k; do drop[$k]=1; done < <(csv_to_lines "$SKIP")
  f=(); for s in "${all_sets[@]}"; do [[ -z "${drop[$s]:-}" ]] && f+=("$s"); done; all_sets=("${f[@]}")
fi

total=${#all_sets[@]}; i=0; passed=()
for s in "${all_sets[@]}"; do
  i=$((i+1)); echo "[mg] ($i/$total) $s"
  st="$(run_row "$s")"; echo "    -> $st"
  [[ "$st" == "pass" ]] && passed+=("$s")
done

# --- maximal achievable reduction: remove EVERY individually-removable set at
# once (proves the reduced-feature install still boots + compiles, and catches
# cross-set interactions a per-set run can't). Grows automatically as more sets
# get decoupled. -------------------------------------------------------------
if [[ -z "$ONLY" && ${#passed[@]} -gt 0 ]]; then
  csv="$(IFS=,; echo "${passed[*]}")"
  echo "[mg] maximal achievable reduction ($csv removed together)"
  # The maximal's disabled-set list (hence its composer.json) varies between
  # runs as sets get decoupled; drop any stale lock so composer re-resolves.
  rm -f "$sandboxes_dir/_max-reduction/composer.lock"
  st="$(run_row _max-reduction "$csv")"; echo "    -> $st"
fi

# --- merge + render -----------------------------------------------------------
echo "[mg] merging into matrix-modulargento.json"
python3 - "$per_set_dir" "$results_dir/matrix-modulargento.json" <<'PY'
import json, os, sys
src, out = sys.argv[1], sys.argv[2]
items = [json.load(open(os.path.join(src, fn))) for fn in sorted(os.listdir(src)) if fn.endswith(".json")]
json.dump({"profile": os.environ.get("PROFILE","mageos-full"),
           "version": os.environ.get("MAGEOS_VERSION",""),
           "source": "modulargento",
           "results": items}, open(out, "w"), indent=2, ensure_ascii=False)
PY

echo "[mg] rendering matrix-modulargento.md"
stock_json="$results_dir/matrix.json"
php "$script_dir/render-modulargento.php" "$results_dir/matrix-modulargento.json" "$stock_json" \
    > "$results_dir/matrix-modulargento.md"

echo "[mg] done. See $results_dir/matrix-modulargento.md"
