#!/usr/bin/env bash
set -euo pipefail

# Cross-repo E2E execution. This is the actual "does migrated code run" proof: takes
# the executable scripts in tests/E2e/executable/ (hand-adapted copies of a subset of tests/E2e/
# expected/'s golden-migrated corpus -- see each script's own doc comment for exactly what was
# adapted and why), installs a REAL univapay/univapay-sdk-compat (from a sibling checkout, via a
# Composer path repository -- same pattern compat's own composer.json already uses for
# apimatic-sdks/univapaypublicapi) into a throwaway consumer project, and executes every script for real
# under PHP 7.2 (the new SDK's actual floor -- see tests/E2e/docker/Dockerfile's own comment for
# why this matters and isn't just "whatever PHP happens to be on the machine") against a real
# Prism mock started from the docs repo's own OpenAPI spec.
#
# Usage:
#   UNIVAPAY_COMPAT_PATH=/path/to/univapay-php-sdk-compat \
#   UNIVAPAY_DOCS_PATH=/path/to/univapay_docs \
#   ./scripts/e2e-execution.sh
#
# Both env vars are REQUIRED (this script does not skip gracefully -- tests/E2e/ExecutionTest.php,
# which shells out to this script, is the one responsible for skipping the whole suite when either
# is unset/invalid, mirroring tests/MapIntegrityTest.php's own skip contract).
#
# Optional:
#   E2E_RESULTS_PATH   Where to copy tests/E2e/execution-runner.php's execution-results.json
#                       after the run (default: tests/E2e/execution-results.json, gitignored).
#                       tests/E2e/ExecutionTest.php points this at its own tmp file.
#   CONTAINER_ENGINE    Force "docker" or "podman" (default: podman if present, else docker --
#                       same precedence as the docs repo's own scripts/test-sdks.sh).

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

: "${UNIVAPAY_COMPAT_PATH:?Set UNIVAPAY_COMPAT_PATH to a univapay-php-sdk-compat checkout}"
: "${UNIVAPAY_DOCS_PATH:?Set UNIVAPAY_DOCS_PATH to a univapay_docs checkout}"

E2E_RESULTS_PATH="${E2E_RESULTS_PATH:-$REPO_ROOT/tests/E2e/execution-results.json}"

if [[ ! -d "$UNIVAPAY_COMPAT_PATH" ]]; then
  echo "UNIVAPAY_COMPAT_PATH ($UNIVAPAY_COMPAT_PATH) is not a directory." >&2
  exit 1
fi
if [[ ! -f "$UNIVAPAY_COMPAT_PATH/composer.json" ]]; then
  echo "UNIVAPAY_COMPAT_PATH ($UNIVAPAY_COMPAT_PATH) does not look like a compat checkout (no composer.json)." >&2
  exit 1
fi
if [[ ! -f "$UNIVAPAY_DOCS_PATH/src/spec/openapi.yaml" ]]; then
  echo "UNIVAPAY_DOCS_PATH ($UNIVAPAY_DOCS_PATH) does not look like a univapay_docs checkout (no src/spec/openapi.yaml)." >&2
  exit 1
fi
if [[ ! -d "$UNIVAPAY_DOCS_PATH/sdk/php" ]]; then
  echo "UNIVAPAY_DOCS_PATH ($UNIVAPAY_DOCS_PATH)/sdk/php not found -- the generated PHP client SDK must be present and committed." >&2
  exit 1
fi

# ── Container runtime (same precedence as docs-repo scripts/test-sdks.sh) ────────────────────
if [[ -n "${CONTAINER_ENGINE:-}" ]]; then
  DOCKER="$CONTAINER_ENGINE"
  if ! command -v "$DOCKER" &>/dev/null; then
    echo "Error: CONTAINER_ENGINE='$DOCKER' not found in PATH" >&2
    exit 1
  fi
elif command -v podman &>/dev/null; then
  DOCKER="podman"
elif command -v docker &>/dev/null; then
  DOCKER="docker"
else
  echo "Error: neither podman nor docker found in PATH" >&2
  exit 1
fi

GREEN='\033[0;32m'; RED='\033[0;31m'; YELLOW='\033[1;33m'; RESET='\033[0m'
info() { echo -e "${YELLOW}▶ $1${RESET}"; }
pass() { echo -e "${GREEN}✔ $1${RESET}"; }
fail() { echo -e "${RED}✖ $1${RESET}"; }

RUN_ID="$$-$(date +%s)"
NETWORK="univapay-migrate-e2e-$RUN_ID"
PRISM_CONTAINER="univapay-migrate-e2e-prism-$RUN_ID"
IMAGE="univapay-migrate-e2e-php72"
CONSUMER_DIR="$(mktemp -d)"

cleanup() {
  "$DOCKER" rm -f "$PRISM_CONTAINER" >/dev/null 2>&1 || true
  "$DOCKER" network rm "$NETWORK" >/dev/null 2>&1 || true
  rm -rf "$CONSUMER_DIR"
}
trap cleanup EXIT

# ── 1. Prism, from the docs repo's own spec ───────────────────────────────────────────────────
info "Starting Prism mock server from $UNIVAPAY_DOCS_PATH/src/spec/openapi.yaml..."
"$DOCKER" network create "$NETWORK" >/dev/null
"$DOCKER" run -d \
  --network "$NETWORK" \
  --name "$PRISM_CONTAINER" \
  -v "$UNIVAPAY_DOCS_PATH/src/spec/openapi.yaml:/spec/openapi.yaml:ro" \
  docker.io/stoplight/prism:4 mock --host 0.0.0.0 /spec/openapi.yaml \
  >/dev/null

info "Waiting for Prism to be ready..."
retries=30
until "$DOCKER" logs "$PRISM_CONTAINER" 2>&1 | grep -q "is listening"; do
  retries=$((retries - 1))
  if [[ $retries -le 0 ]]; then
    fail "Prism failed to start."
    "$DOCKER" logs "$PRISM_CONTAINER" 2>&1 | tail -20
    exit 1
  fi
  sleep 1
done
pass "Prism ready at http://$PRISM_CONTAINER:4010 (network: $NETWORK)"

# ── 2. Build the PHP 7.2 + composer execution image ───────────────────────────────────────────
info "Building execution image ($IMAGE)..."
"$DOCKER" build -q -t "$IMAGE" -f "$REPO_ROOT/tests/E2e/docker/Dockerfile" "$REPO_ROOT/tests/E2e/docker" >/dev/null

# ── 3. Throwaway consumer project: composer.json with two path repositories ──────────────────
# Mirrors the compat repo's OWN composer.json "repositories" pattern for
# apimatic-sdks/univapaypublicapi (docs sdk/php, with a synthetic "versions" override since that
# path has no version of its own) -- plus a second path repository for compat itself, for the
# same reason (path repositories declared in a DEPENDENCY's composer.json are never inherited by
# a different root project, so compat's own path-repo entry for apimatic-sdks/univapaypublicapi
# does not carry over here; both must be declared on THIS throwaway project's own composer.json).
#
# The docs-repo generated SDK tree keeps APIMatic's placeholder package name
# apimatic-sdks/univapaypublicapi (univapay/client-sdk is the PUBLIC Packagist name the publishing
# profile applies at publish time, never a name the tree itself declares) -- so the second path
# repository's "versions" override below must key on apimatic-sdks/univapaypublicapi, matching
# compat's own composer.json requirement, not the eventual published name.
info "Writing throwaway consumer project at $CONSUMER_DIR..."
cat > "$CONSUMER_DIR/composer.json" <<'EOF'
{
    "name": "e2e/executable-consumer",
    "description": "Throwaway consumer project for the cross-repo E2E execution suite -- never committed, built fresh by scripts/e2e-execution.sh.",
    "require": {
        "php": "^7.2",
        "univapay/univapay-sdk-compat": "1.1.0"
    },
    "repositories": [
        {
            "type": "path",
            "url": "/workspace/compat",
            "options": {
                "symlink": false,
                "versions": { "univapay/univapay-sdk-compat": "1.1.0" }
            }
        },
        {
            "type": "path",
            "url": "/workspace/docs-sdk-php",
            "options": {
                "symlink": false,
                "versions": { "apimatic-sdks/univapaypublicapi": "1.1.0" }
            }
        }
    ],
    "minimum-stability": "stable",
    "prefer-stable": true,
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "dealerdirect/phpcodesniffer-composer-installer": true
        }
    }
}
EOF

# ── 4. composer install (--no-dev: only compat's own RUNTIME deps are needed to execute the
#      scripts -- phpcs/phpunit/phpcompatibility etc. from compat's require-dev are irrelevant
#      here and would only cost time + an unnecessary dealerdirect plugin prompt) ──────────────
info "Running composer install (compat: $UNIVAPAY_COMPAT_PATH, client-sdk: $UNIVAPAY_DOCS_PATH/sdk/php)..."
"$DOCKER" run --rm \
  -v "$CONSUMER_DIR:/workspace/consumer" \
  -v "$UNIVAPAY_COMPAT_PATH:/workspace/compat:ro" \
  -v "$UNIVAPAY_DOCS_PATH/sdk/php:/workspace/docs-sdk-php:ro" \
  -w /workspace/consumer \
  "$IMAGE" \
  composer install --no-dev --prefer-dist --no-progress

# ── 5. Execute every script for real, under PHP 7.2, against the real Prism ───────────────────
info "Executing tests/E2e/executable/*.php under PHP 7.2 against Prism..."
set +e
"$DOCKER" run --rm \
  --network "$NETWORK" \
  -v "$CONSUMER_DIR:/workspace/consumer" \
  -v "$REPO_ROOT/tests/E2e:/workspace/e2e:ro" \
  -e "UNIVAPAY_PRISM_URL=http://$PRISM_CONTAINER:4010" \
  -e "E2E_CONSUMER_ROOT=/workspace/consumer" \
  -w /workspace/consumer \
  "$IMAGE" \
  php /workspace/e2e/execution-runner.php
RUN_EXIT=$?
set -e

mkdir -p "$(dirname "$E2E_RESULTS_PATH")"
if [[ -f "$CONSUMER_DIR/execution-results.json" ]]; then
  cp "$CONSUMER_DIR/execution-results.json" "$E2E_RESULTS_PATH"
  info "Wrote $E2E_RESULTS_PATH"
else
  fail "execution-runner.php did not write execution-results.json -- see output above."
fi

if [[ $RUN_EXIT -eq 0 ]]; then
  pass "All executable scripts passed."
else
  fail "One or more executable scripts failed (see $E2E_RESULTS_PATH)."
fi

exit $RUN_EXIT
