# Twig Preview Toolkit

Preview and screenshot OpenEMR Twig templates **without Docker, a database, or
the full application kernel**. This is designed for cloud / remote coding
sessions (e.g. Claude Code on the web) where spinning up the Docker stack is
impractical, but you still want to *see* a UI change.

It reuses the exact rendering path as the isolated render tests
(`tests/Tests/Isolated/Common/Twig/TwigTemplateRenderTest.php`): the real
`TwigContainer`, with translation disabled and `setupHeader()` stubbed. So a
preview reflects how the application actually renders a template.

## Prerequisites (one-time per fresh session)

```bash
composer install --no-scripts      # provides vendor/ and TwigContainer
npm install playwright --no-save   # only needed for screenshots / bundles
```

Chromium is pre-installed in the cloud environment at `/opt/pw-browsers/chromium`
(no `playwright install` needed). Locally, set `PLAYWRIGHT_CHROMIUM_PATH` if your
Chromium lives elsewhere.

## What's here

| File | Purpose |
|------|---------|
| `render.php` | Render one template to HTML on stdout (fastest, no browser). |
| `serve.php`  | Dev-server router: renders templates **and** serves real `/public` assets so styling resolves. |
| `shoot.mjs`  | Screenshot a preview URL at one or more viewports (PNG). |
| `bundle.mjs` | Inline CSS/images into a single self-contained HTML file for publishing as an Artifact. |
| `params/`    | Example JSON parameter files (template variables). |

## Tier 1 — Quick HTML (structure & content)

```bash
php tools/preview/render.php portal/login/autologin.html.twig \
    tools/preview/params/autologin.json > /tmp/autologin.html
```

Raw output references `/public/...` assets by path, so it is **unstyled** on its
own. Good for reviewing markup, escaping, and conditional logic. To get a styled,
self-contained page you can open anywhere (or publish as an Artifact), use the
dev-server + `bundle.mjs` (below).

## Tier 2 — Styled previews & screenshots (recommended)

Start the dev-server from the repository root:

```bash
php -S 127.0.0.1:8400 tools/preview/serve.php
```

Open a template (note the `t`, `p`, and `css` query params):

```
http://127.0.0.1:8400/?t=portal/login/autologin.html.twig&p=tools/preview/params/autologin.json
```

| Param | Meaning |
|-------|---------|
| `t`   | Template name (required), e.g. `portal/login/autologin.html.twig`. |
| `p`   | Repo-relative JSON parameter file. |
| `css` | Comma-separated stylesheet URLs to inject into `<head>`. |

### Styling the preview

`setupHeader()` is stubbed, so isolated previews carry **no application CSS** by
default (the markup is correct but unstyled). Two ways to get real styling:

- Build the themes once, then inject one:
  ```bash
  npm install && npm run gulp-build   # produces public/themes/*.css
  ```
  ```
  ...&css=/public/themes/style_light.css
  ```
- Or point `css` at any stylesheet the dev-server can serve from disk. The
  dev-server serves every existing file under the repo root, so
  `/public/...` paths resolve.

Screenshot it at desktop / tablet / mobile:

```bash
URL="http://127.0.0.1:8400/?t=portal/login/autologin.html.twig&p=tools/preview/params/autologin.json"
node tools/preview/shoot.mjs "$URL" --full
# -> tools/preview/out/preview-1280x800.png, preview-768x1024.png, preview-375x812.png
```

Pick specific viewports:

```bash
node tools/preview/shoot.mjs "$URL" --viewport=1440x900 --viewport=390x844
```

## Publish a self-contained page (Artifact)

```bash
node tools/preview/bundle.mjs "$URL" --out=tools/preview/out/autologin.html
```

`autologin.html` inlines stylesheets and images as data URIs, so it renders
correctly under the Artifact CSP and offline.

## Adding a new preview

1. Find the template variables. The isolated render test's `renderCaseProvider()`
   is the best source of realistic parameter sets — copy one into a JSON file
   under `params/`.
2. Render or serve it as above.

## Scope & limitations

- **Templates only.** This previews Twig output. It does not run controllers,
  Angular, or database queries. For a fully interactive authenticated page you
  still need the app running (Tier 3).
- **Translation is disabled**, matching the isolated tests — text appears in
  untranslated English with escaping applied.
- **`setupHeader()` is stubbed** — its output is replaced with an HTML comment.
- **Dev-only.** Nothing here is wired into the application or shipped to
  production; it lives entirely under `tools/`.
