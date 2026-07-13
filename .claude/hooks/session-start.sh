#!/bin/bash
#
# SessionStart hook: warm the Twig preview toolkit (tools/preview/) so visual
# proofing works without waiting for the Docker stack.
#
# Runs asynchronously so it never delays session startup. It is idempotent:
# once the container caches vendor/ and the Playwright package, subsequent
# sessions skip the slow steps. Remote (Claude Code on the web) only.
#
# Scope note: this installs production dependencies only (--no-dev). Full dev
# deps (phpstan, phpunit, rector) are intentionally skipped -- their dist
# downloads are blocked by the egress proxy in this environment, and the
# preview toolkit does not need them.

set -euo pipefail

echo '{"async": true, "asyncTimeout": 420000}'

# Only run in the remote (web) environment; local machines have their own setup.
if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
    exit 0
fi

cd "${CLAUDE_PROJECT_DIR:-.}"

export COMPOSER_ALLOW_SUPERUSER=1
export COMPOSER_MEMORY_LIMIT=-1

# The environment injects an invalid "proxy-injected" placeholder into
# Composer's global github-oauth token, which Composer rejects as malformed.
# Clear it so installs authenticate through the proxy's git rewrite instead.
COMPOSER_HOME_DIR="$(composer config --global home 2>/dev/null || echo "$HOME/.config/composer")"
AUTH_JSON="$COMPOSER_HOME_DIR/auth.json"
if [ -f "$AUTH_JSON" ]; then
    php -r '
        $p = $argv[1];
        $d = json_decode((string) file_get_contents($p), true);
        if (is_array($d) && !empty($d["github-oauth"])) {
            $d["github-oauth"] = new stdClass();
            file_put_contents($p, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    ' "$AUTH_JSON" || true
fi

# Install production PHP dependencies (provides vendor/ + TwigContainer).
# Skip when already present so cached containers start instantly.
if [ ! -f vendor/autoload.php ]; then
    composer install --no-dev --no-scripts --no-interaction --prefer-source
fi

# Ensure the Playwright npm package is available for screenshots. Chromium
# itself is pre-installed in the web environment (PLAYWRIGHT_BROWSERS_PATH).
if ! node -e "require('playwright')" >/dev/null 2>&1; then
    npm install playwright --no-save --no-audit --no-fund
fi

echo "Twig preview toolkit ready: tools/preview/preview.sh <template> [params.json]"
