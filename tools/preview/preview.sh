#!/usr/bin/env bash
#
# One-command Twig preview: boot the dev-server, screenshot a template at one or
# more viewports, and tear the server down. No Docker, no database.
#
# Usage:
#   tools/preview/preview.sh <template> [params.json] [options]
#
# Options:
#   --css=URL          Stylesheet(s) to inject (comma-separated). Repeatable.
#                      e.g. --css=/public/themes/style_light.css
#   --viewport=WxH     Viewport to capture. Repeatable. Defaults to
#                      1280x800, 768x1024 and 375x812.
#   --full             Capture the full scrollable page.
#   --out=DIR          Output directory (default: tools/preview/out).
#   --port=N           Dev-server port (default: 8400, or $PREVIEW_PORT).
#
# Examples:
#   tools/preview/preview.sh portal/login/autologin.html.twig tools/preview/params/autologin.json
#   tools/preview/preview.sh patient/card/appointments.html.twig params/appts.json \
#       --css=/public/themes/style_light.css --viewport=1440x900 --full
#
# Prints the paths of the generated PNG files on success.

set -euo pipefail

# Resolve repository root from this script's location so it works from anywhere.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
cd "$ROOT"

if [[ $# -lt 1 || "$1" == -* ]]; then
    grep '^#' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//' | sed '1d'
    exit 1
fi

TEMPLATE="$1"; shift

PARAMS=""
if [[ $# -gt 0 && "$1" != -* ]]; then
    PARAMS="$1"; shift
fi

PORT="${PREVIEW_PORT:-8400}"
CSS=""
SHOOT_ARGS=()

for arg in "$@"; do
    case "$arg" in
        --css=*)      CSS="${CSS:+$CSS,}${arg#--css=}" ;;
        --port=*)     PORT="${arg#--port=}" ;;
        --viewport=*) SHOOT_ARGS+=("$arg") ;;
        --full)       SHOOT_ARGS+=("--full") ;;
        --out=*)      SHOOT_ARGS+=("$arg") ;;
        *) echo "Unknown option: $arg" >&2; exit 1 ;;
    esac
done

if [[ ! -f vendor/autoload.php ]]; then
    echo "vendor/ missing. Run: composer install --no-dev --no-scripts" >&2
    exit 1
fi

# Build the preview URL.
urlencode() { php -r 'echo rawurlencode($argv[1]);' "$1"; }
URL="http://127.0.0.1:${PORT}/?t=$(urlencode "$TEMPLATE")"
[[ -n "$PARAMS" ]] && URL="${URL}&p=$(urlencode "$PARAMS")"
[[ -n "$CSS" ]] && URL="${URL}&css=$(urlencode "$CSS")"

# Start the dev-server and guarantee teardown on any exit.
php -S "127.0.0.1:${PORT}" tools/preview/serve.php >/dev/null 2>&1 &
SERVER_PID=$!
cleanup() { kill "$SERVER_PID" 2>/dev/null || true; }
trap cleanup EXIT

# Wait for the server to accept connections (up to ~5s).
for _ in $(seq 1 50); do
    if curl -sf -o /dev/null "http://127.0.0.1:${PORT}/" 2>/dev/null \
        || curl -s -o /dev/null "http://127.0.0.1:${PORT}/"; then
        break
    fi
    sleep 0.1
done

PLAYWRIGHT_CHROMIUM_PATH="${PLAYWRIGHT_CHROMIUM_PATH:-/opt/pw-browsers/chromium}" \
    node tools/preview/shoot.mjs "$URL" "${SHOOT_ARGS[@]}"
