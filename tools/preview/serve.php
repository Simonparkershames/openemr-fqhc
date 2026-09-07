<?php

/**
 * Router for the standalone Twig preview dev-server.
 *
 * Renders OpenEMR Twig templates through the application's own Twig + Header
 * pipeline while serving the repository's real /public assets, so previews match
 * a live Docker instance as closely as possible without a database. By default
 * the <head> carries the real config-driven assets (theme CSS, Bootstrap,
 * scripts); see bootstrap.php for fidelity guarantees and known deltas.
 *
 * Start it from the repository root:
 *   php -S 127.0.0.1:8400 tools/preview/serve.php
 *
 * Then open, e.g.:
 *   http://127.0.0.1:8400/?t=portal/login/autologin.html.twig&p=tools/preview/params/autologin.json
 *
 * Query parameters:
 *   t     template name (required), e.g. portal/login/autologin.html.twig
 *   p     JSON parameter file, repo-relative
 *   theme main theme CSS filename (default style_light.css) to match your instance
 *   stub  set to 1 to use the lightweight header stub (structure only, no styling)
 *   css   comma-separated *extra* stylesheet URLs to inject (rarely needed now
 *         that the real theme loads by default)
 *
 * Any request whose path maps to an existing file under the repository root
 * (for example /public/themes/style_light.css) is served as-is; every other
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

require_once __DIR__ . '/bootstrap.php';

$root = preview_root();
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
$theme = isset($_GET['theme']) && is_string($_GET['theme']) ? $_GET['theme'] : 'style_light.css';
$stub = isset($_GET['stub']) && $_GET['stub'] === '1';

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
    $html = preview_twig(!$stub, '', $theme)->render($template, preview_load_params(
        $paramsFile !== null ? $root . '/' . ltrim($paramsFile, '/') : null
    ));

    $links = cssLinks($css);
    if ($links !== '') {
        // Optional extra stylesheets, injected just before </head>.
        $html = str_contains($html, '</head>')
            ? str_replace('</head>', $links . '</head>', $html)
            : $links . $html;
    }
    // nosemgrep: echoed-request -- $html is Twig-rendered output (autoescaped); the only manually
    // concatenated request input ($css, via cssLinks()) is htmlspecialchars(ENT_QUOTES)-escaped there.
    // Dev-only preview server, bound to 127.0.0.1; not application runtime.
    echo $html;
} catch (\Throwable $e) {
    http_response_code(500);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:2rem">'
        . '<h1>Render failed</h1><pre>' . htmlspecialchars($e->getMessage()) . '</pre>'
        . '</body></html>';
}

return true;
