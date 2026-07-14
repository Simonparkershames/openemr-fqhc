<?php

/**
 * Standalone Twig template preview renderer.
 *
 * Renders a single OpenEMR Twig template to HTML using nothing but the
 * application's own Twig + Header pipeline -- no database, no kernel, no Docker.
 * By default the <head> carries the real config-driven assets (theme CSS,
 * Bootstrap, scripts) so output matches a live instance; see bootstrap.php for
 * the exact fidelity guarantees and known deltas.
 *
 * Usage:
 *   php tools/preview/render.php <template> [params.json] [--stub] > out.html
 *   php tools/preview/render.php portal/login/autologin.html.twig \
 *       tools/preview/params/autologin.json > out.html
 *
 * --stub renders with the lightweight header stub (structure only, no styling),
 * matching the isolated render tests.
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

$args = array_slice($argv, 1);
$realHeader = !in_array('--stub', $args, true);
$args = array_values(array_filter($args, static fn (string $a): bool => $a !== '--stub'));

$template = $args[0] ?? null;
if ($template === null) {
    fwrite(STDERR, "Usage: php tools/preview/render.php <template> [params.json] [--stub] > out.html\n");
    exit(1);
}

$parameters = preview_load_params($args[1] ?? null);

try {
    echo preview_twig($realHeader)->render($template, $parameters);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Render failed: ' . $e->getMessage() . "\n");
    exit(1);
}
