#!/usr/bin/env bash
#
# Build OpenEMR theme CSS for previews, without the full gulp asset pipeline.
#
# The stock `npm run gulp-build` does not work in the restricted cloud sandbox:
# its `ingest` step copies vendor assets fetched from external URLs (napa) that
# the egress proxy blocks, so Bootstrap's SCSS never reaches the path the theme
# build imports. This script does the scoped equivalent for a single theme:
# it bridges the Bootstrap and FontAwesome SCSS from node_modules, provides the
# FQHC design tokens, and compiles the theme with the sass CLI.
#
# It is a close approximation of gulp's --dev output. The one difference is
# vendor prefixing (gulp pipes through autoprefixer, which is not installed
# here); this is negligible in Chromium, and previews should be certified
# against the real app with tools/preview/paritydiff.mjs regardless.
#
# Usage:
#   tools/preview/build-themes.sh [theme.css ...]     # default: style_light.css
#
# Produces public/themes/<theme>.css for each requested theme.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
cd "${ROOT}"

SASS="node_modules/.bin/sass"
if [[ ! -x "${SASS}" ]]; then
    echo "sass not found (${SASS}). Run: npm install --ignore-scripts" >&2
    exit 1
fi

# Bridge vendor SCSS the themes @import from /public/assets (gulp's ingest step).
mkdir -p public/assets public/assets/css public/themes
[[ -e node_modules/bootstrap ]] && ln -sfn "${ROOT}/node_modules/bootstrap" public/assets/bootstrap
[[ -e node_modules/@fortawesome ]] && ln -sfn "${ROOT}/node_modules/@fortawesome" public/assets/@fortawesome

# FQHC design tokens the light theme imports from /public/assets/css/tokens.css.
TOKENS_SRC="interface/modules/custom_modules/oe-module-fqhc/public/assets/css/tokens.css"
if [[ -f "${TOKENS_SRC}" && ! -f public/assets/css/tokens.css ]]; then
    cp "${TOKENS_SRC}" public/assets/css/tokens.css
fi

themes=("$@")
[[ ${#themes[@]} -eq 0 ]] && themes=("style_light.css")

for css in "${themes[@]}"; do
    name="${css%.css}"
    src="interface/themes/oe-styles/${name}.scss"
    if [[ ! -f "${src}" ]]; then
        echo "Skipping ${css}: no source at ${src}" >&2
        continue
    fi

    # Mirror gulp's styles_style_uni: set the compact flag and inject the
    # Bootstrap import in place of the // bs4import marker. Compile in the source
    # directory so the theme's relative @imports resolve.
    tmp="interface/themes/oe-styles/_preview_${name}.scss"
    {
        # shellcheck disable=SC2016 # literal SCSS variable; $ must not be expanded by the shell
        echo '$compact-theme: false;'
        sed 's#// bs4import#@import "public/assets/bootstrap/scss/bootstrap";#' "${src}"
    } > "${tmp}"

    "${SASS}" --load-path=. --load-path=node_modules --quiet --quiet-deps \
        "${tmp}" "public/themes/${css}"
    rm -f "${tmp}"

    size=$(wc -c < "public/themes/${css}")
    echo "built public/themes/${css} (${size} bytes)"
done
