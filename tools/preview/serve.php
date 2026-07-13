<?php

/**
 * Router for the standalone Twig preview dev-server.
 *
 * Runs OpenEMR Twig templates through the isolated TwigContainer while serving
 * the repository's real /public assets, so Bootstrap CSS, images, and JS
 * resolve and the preview looks like the running application -- all without a
 * database or the Docker stack.
 *
 * Start it from the repository root:
 *   php -S 127.0.0.1:8400 tools/preview/serve.php
 *
 * Then open, e.g.:
 *   http://127.0.0.1:8400/?t=portal/login/autologin.html.twig&p=tools/preview/params/autologin.json
 *
 * Query parameters:
 *   t   template name (required), e.g. portal/login/autologin.html.twig
 *   p   JSON parameter file, repo-relative, e.g. tools/preview/params/autologin.json
 *   css comma-separated stylesheet URLs to inject into <head>. Because
 *       setupHeader() is stubbed, isolated previews carry no application CSS;
 *       point this at a built theme (e.g. /public/themes/style_light.css after
 *       `npm run gulp-build`) to preview a template with real styling.
 *
 * Any request whose path maps to an existing file under the repository root
 * (for example /public/images/logo-full-con.png) is served as-is; every other
 * request renders the template named by the "t" query parameter.
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
use Twig\Environment;
use Twig\TwigFunction;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$root = dirname(__DIR__, 2);
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uriPath = is_string($uriPath) ? rawurldecode($uriPath) : '/';

// Serve real static assets (CSS, JS, images) straight from disk so previews
// match the running app. realpath() also guards against path traversal.
$candidate = realpath($root . $uriPath);
if (
    $uriPath !== '/'
    && $candidate !== false
    && str_starts_with($candidate, $root . DIRECTORY_SEPARATOR)
    && is_file($candidate)
) {
    return false; // Let PHP's built-in server serve the file.
}

/**
 * Build a Twig environment wired for isolated preview rendering.
 *
 * Mirrors tools/preview/render.php and the isolated render test: translation
 * disabled, setupHeader() stubbed.
 */
function previewEnvironment(string $root): Environment
{
    $GLOBALS['fileroot'] ??= $root;
    $GLOBALS['date_display_format'] ??= 0;
    $GLOBALS['disable_translation'] = true;

    $twig = (new TwigContainer())->getTwig();
    $twig->addFunction(new TwigFunction(
        'setupHeader',
        static fn (): string => '<!-- setupHeader stub -->',
        ['is_safe' => ['html']]
    ));

    return $twig;
}

/**
 * Decode a JSON parameter file into template variables.
 *
 * @return array<string, mixed>
 */
function loadParameters(string $root, ?string $relPath): array
{
    if ($relPath === null || $relPath === '') {
        return [];
    }

    $full = realpath($root . '/' . ltrim($relPath, '/'));
    if ($full === false || !str_starts_with($full, $root . DIRECTORY_SEPARATOR) || !is_file($full)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($full), true);
    if (!is_array($decoded)) {
        return [];
    }

    /** @var array<string, mixed> $decoded */
    return $decoded;
}

/**
 * Build <link rel="stylesheet"> tags for a comma-separated list of URLs.
 */
function cssLinks(?string $cssParam): string
{
    if ($cssParam === null || $cssParam === '') {
        return '';
    }

    $links = '';
    foreach (explode(',', $cssParam) as $href) {
        $href = trim($href);
        if ($href === '') {
            continue;
        }
        $links .= '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES) . "\">\n";
    }

    return $links;
}

$template = isset($_GET['t']) && is_string($_GET['t']) ? $_GET['t'] : null;
$paramsFile = isset($_GET['p']) && is_string($_GET['p']) ? $_GET['p'] : null;
$css = isset($_GET['css']) && is_string($_GET['css']) ? $_GET['css'] : null;

header('Content-Type: text/html; charset=utf-8');

if ($template === null) {
    http_response_code(400);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:2rem">'
        . '<h1>Twig preview server</h1>'
        . '<p>Pass a template with <code>?t=&lt;template&gt;</code>, e.g.:</p>'
        . '<pre>/?t=portal/login/autologin.html.twig&amp;p=tools/preview/params/autologin.json</pre>'
        . '</body></html>';
    return true;
}

try {
    $html = previewEnvironment($root)->render($template, loadParameters($root, $paramsFile));
    $links = cssLinks($css);
    if ($links !== '') {
        // Inject stylesheet links just before </head> so a stubbed setupHeader()
        // does not leave the preview unstyled. Fall back to prepending when the
        // template has no </head> (e.g. a bare partial).
        $html = str_contains($html, '</head>')
            ? str_replace('</head>', $links . '</head>', $html)
            : $links . $html;
    }
    echo $html;
} catch (\Throwable $e) {
    http_response_code(500);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:2rem">'
        . '<h1>Render failed</h1><pre>' . htmlspecialchars($e->getMessage()) . '</pre>'
        . '</body></html>';
}

return true;
