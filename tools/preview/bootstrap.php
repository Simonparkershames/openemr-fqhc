<?php

/**
 * Shared bootstrap for the Twig preview toolkit.
 *
 * Builds a Twig environment that renders templates the way the running
 * application does, so previews match a real Docker instance as closely as is
 * possible without a database:
 *
 *  - The `setupHeader()` Twig function emits the *real* asset tags. It reproduces
 *    the theme-path expansion from interface/globals.php and then calls the
 *    application's own OpenEMR\Core\Header::setupAssets(), so the <head> carries
 *    the same theme CSS, Bootstrap, and scripts that config/config.yaml drives
 *    in production. This replaces the earlier hand-picked ?css= guesswork, which
 *    risked looking different from the real environment.
 *
 * Known, deliberate deltas from a live instance (documented in README.md):
 *  - The `?v=` cache-buster query string is omitted (visually irrelevant).
 *  - Kernel-dispatched *module* scripts/styles are skipped (they need the
 *    application kernel); core theme/Bootstrap assets are unaffected.
 *  - The favicon link is omitted (never visible in a screenshot).
 *  - Translation is disabled, matching the isolated render tests.
 *
 * Styling requires the referenced files to exist on disk, i.e. run
 * `npm run gulp-build` once so /public/themes/*.css are present.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tools\Preview;

use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Core\Header;
use Twig\Environment;
use Twig\TwigFunction;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

/**
 * Repository root (two levels up from this file).
 */
function preview_root(): string
{
    return dirname(__DIR__, 2);
}

/**
 * Seed the globals the real asset pipeline reads, reproducing the theme-path
 * expansion in interface/globals.php so setupAssets() resolves the same paths a
 * live instance does. The default theme (style_light.css) is the repo default
 * from library/globals.inc.php; override via the $theme argument if your
 * instance runs a different General Theme.
 */
function preview_seed_globals(string $webRoot = '', string $theme = 'style_light.css'): void
{
    $root = preview_root();

    $GLOBALS['fileroot'] ??= $root;
    $GLOBALS['webroot'] = $webRoot;
    $GLOBALS['assets_static_relative'] = $webRoot . '/public/assets';
    $GLOBALS['assetsDir'] = $root . '/public/assets';
    // interface/globals.php:480-481 -- expand the theme name into a full path.
    $GLOBALS['css_header'] = $webRoot . '/public/themes/' . $theme;
    $GLOBALS['compact_header'] = $webRoot . '/public/themes/compact_' . $theme;
    // Portal templates (e.g. portal/login/*) load portal-theme instead; the
    // portal General Theme default is also style_light.css.
    $GLOBALS['portal_css_header'] = $webRoot . '/public/themes/' . $theme;
    $GLOBALS['enable_compact_mode'] = false;
    $GLOBALS['theme_tabs_layout'] = 'tabs_style_full.css';
    $GLOBALS['date_display_format'] ??= 0;
    // Bypass database-dependent translation lookups.
    $GLOBALS['disable_translation'] = true;

    // setupAssets() reads these from the request when appending versions.
    $_SERVER['SCRIPT_NAME'] ??= '/preview';
    $_SERVER['REQUEST_URI'] ??= '/preview';
}

/**
 * Render the real <head> asset block, minus the kernel-only module events.
 *
 * Mirrors the non-dispatch portion of Header::setupHeader(): the required meta
 * tags (the viewport tag matters for responsive screenshots) followed by the
 * config-driven core assets. Requested $assets are honored just as templates
 * pass them (e.g. setupHeader(['datetime-picker'])).
 *
 * @param list<string> $assets
 */
function preview_real_header(array $assets = []): string
{
    $meta = "\n<meta charset=\"utf-8\" />\n"
        . "<meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\" />\n"
        . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1, shrink-to-fit=no\" />\n"
        . "<!-- preview: favicon and module-injected assets omitted -->\n";

    return $meta . Header::setupAssets($assets, true, false);
}

/**
 * Build the preview Twig environment.
 *
 * When $realHeader is true (the default), setupHeader() emits real assets for
 * production-faithful styling. Pass false to fall back to the lightweight stub
 * used by the isolated render tests (structure only, no styling).
 */
function preview_twig(bool $realHeader = true, string $webRoot = '', string $theme = 'style_light.css'): Environment
{
    preview_seed_globals($webRoot, $theme);

    $twig = (new TwigContainer())->getTwig();

    $setupHeader = $realHeader
        ? static fn (array $assets = []): string => preview_real_header($assets)
        : static fn (): string => '<!-- setupHeader stub -->';

    $twig->addFunction(new TwigFunction('setupHeader', $setupHeader, ['is_safe' => ['html']]));

    return $twig;
}

/**
 * Decode a JSON parameter file into template variables.
 *
 * @return array<string, mixed>
 */
function preview_load_params(?string $path): array
{
    if ($path === null || $path === '') {
        return [];
    }

    $json = @file_get_contents($path);
    if ($json === false) {
        return [];
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }

    /** @var array<string, mixed> $decoded */
    return $decoded;
}
