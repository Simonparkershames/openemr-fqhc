# Twig Preview Toolkit

Preview and screenshot OpenEMR Twig templates **without Docker, a database, or
the full application kernel**. Designed for cloud / remote coding sessions
(e.g. Claude Code on the web) where spinning up the Docker stack is impractical,
but you still want to *see* a UI change.

Rendering goes through the application's own `TwigContainer`, and by default the
`<head>` is produced by the real `OpenEMR\Core\Header` asset pipeline — the same
`config/config.yaml`-driven theme CSS, Bootstrap, and scripts the running app
emits. The goal is that a preview looks like a real Docker instance, not merely
"styled". See **Fidelity** below for the exact guarantees and known deltas.

## Prerequisites (one-time per fresh session)

```bash
composer install --no-dev --no-scripts --prefer-source   # vendor/ + TwigContainer
npm install --ignore-scripts                              # sass toolchain for themes
npm install playwright --no-save                          # screenshots / diffs
tools/preview/build-themes.sh                             # public/themes/style_light.css
```

On the web, the SessionStart hook runs all four automatically. Chromium is
pre-installed at `/opt/pw-browsers/chromium` (no `playwright install`); locally,
set `PLAYWRIGHT_CHROMIUM_PATH` if Chromium lives elsewhere.

Use `tools/preview/build-themes.sh`, **not** `npm run gulp-build`: the stock
gulp build's `ingest` step fetches vendor assets from external URLs the egress
proxy blocks, so it fails in this sandbox. `build-themes.sh` compiles the theme
directly from `node_modules` (Bootstrap + FontAwesome SCSS + FQHC tokens). Pass
theme names to build others, e.g. `tools/preview/build-themes.sh style_dark.css`.
Without a built theme, the real `<link>` is still emitted but resolves to a
missing file, so the page renders unstyled.

## What's here

| File | Purpose |
|------|---------|
| `preview.sh`    | **Start here.** One command: serve → screenshot → tear down. |
| `render.php`    | Render one template to HTML on stdout (fastest, no browser). |
| `serve.php`     | Dev-server router: renders templates with the real header **and** serves real `/public` assets. |
| `shoot.mjs`     | Screenshot a preview URL at one or more viewports (PNG). |
| `paritydiff.mjs`| Certify a preview against the live app: pixel-diff preview vs. Docker. |
| `bundle.mjs`    | Inline CSS/images into a single self-contained HTML file for an Artifact. |
| `build-themes.sh`| Compile theme CSS without the (sandbox-broken) gulp pipeline. |
| `bootstrap.php` | Shared setup: real header + theme globals (the fidelity core). |
| `params/`       | Example JSON parameter files (template variables). |

## Quick start — one command

```bash
tools/preview/preview.sh portal/login/autologin.html.twig \
    tools/preview/params/autologin.json --full
# -> tools/preview/out/preview-1280x800.png, preview-768x1024.png, preview-375x812.png
```

Specific viewports: `--viewport=1440x900 --viewport=390x844`. Match a different
theme: pass `--css` is no longer needed — set the theme via the dev-server
`theme` param (below) if your instance isn't on the default.

## Manual dev-server

```bash
php -S 127.0.0.1:8400 tools/preview/serve.php
```

```
http://127.0.0.1:8400/?t=portal/login/autologin.html.twig&p=tools/preview/params/autologin.json
```

| Param  | Meaning |
|--------|---------|
| `t`    | Template name (required), e.g. `portal/login/autologin.html.twig`. |
| `p`    | Repo-relative JSON parameter file. |
| `theme`| Main theme CSS filename (default `style_light.css`) — set to match your instance's General Theme. |
| `stub` | `1` to use the lightweight header stub (structure only, no styling). |
| `css`  | Comma-separated *extra* stylesheets to inject (rarely needed now the real theme loads). |

Screenshot a running server directly with `shoot.mjs`:

```bash
node tools/preview/shoot.mjs "<url>" --full --viewport=375x812
```

## Certify parity against the real app

The only way to *know* a preview matches Docker is to compare it. During a
one-time Docker run, screenshot the same view in both and pixel-diff:

```bash
node tools/preview/paritydiff.mjs \
  --preview="http://127.0.0.1:8400/?t=portal/login/autologin.html.twig&p=tools/preview/params/autologin.json" \
  --live="http://localhost:8300/<the same view in the running app>" \
  --viewport=1280x800
# prints mismatch %, writes composite.png (preview | live | diff)
```

Near-0% → trust the fast loop for that template. Divergent → that template needs
the full app (see Fidelity). Re-certify when themes or assets change.

## Publish a self-contained page (Artifact)

```bash
node tools/preview/bundle.mjs "<url>" --out=tools/preview/out/autologin.html
```

Inlines stylesheets and images as data URIs, so it renders under the Artifact
CSP and offline.

## Fidelity — how close to Docker, and where it stops

**Matched:** the real config-driven `<head>` (theme CSS, Bootstrap, core
scripts, viewport meta), served from the same physical `/public` assets the app
uses. For full-page templates (login, portal) this reaches near-parity.

**Known deltas (small / invisible):**
- `?v=` cache-buster query strings are omitted.
- Kernel-dispatched *module* scripts/styles are skipped (they need the app
  kernel); core theme/Bootstrap assets are unaffected.
- The favicon link is omitted (never visible in a screenshot).
- Translation is disabled — text is untranslated English with escaping applied.

**Inherent gaps (certify against the real app, don't trust blindly):**
- **Partials** (e.g. patient-dashboard cards) rendered *outside* their parent
  page lack the surrounding grid/container — widths and spacing differ no matter
  the CSS.
- **JS/Angular-driven UI** that restyles or populates after load.
- **Real-data-driven layout** (long names, many rows) vs. mock params.

**Dev-only.** Nothing here is wired into the application or shipped to
production; it lives entirely under `tools/`.
