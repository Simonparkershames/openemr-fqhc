<?php

/**
 * Standalone Twig template preview renderer.
 *
 * Renders a single OpenEMR Twig template to HTML using nothing but the
 * TwigContainer -- no database, no application kernel, no Docker stack. This is
 * the same rendering path exercised by the isolated render tests
 * (tests/Tests/Isolated/Common/Twig/TwigTemplateRenderTest.php), so previews
 * stay faithful to how the application renders a template.
 *
 * Usage:
 *   php tools/preview/render.php <template> [params.json] > out.html
 *   php tools/preview/render.php portal/login/autologin.html.twig \
 *       tools/preview/params/autologin.json > out.html
 *
 * The optional JSON file provides the template variables. When omitted, the
 * template is rendered with an empty parameter set.
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

/**
 * Resolve the repository root (three levels up from this file).
 */
function fileroot(): string
{
    return dirname(__DIR__, 2);
}

/**
 * Build a Twig environment wired for isolated preview rendering.
 *
 * Mirrors the setup in TwigTemplateRenderTest: translation is disabled so
 * xlt()/xla() apply escaping only, and setupHeader() is stubbed because the
 * real implementation needs the kernel and event dispatcher. Preview rendering
 * verifies template structure and appearance, not header generation.
 */
function previewEnvironment(): Environment
{
    $GLOBALS['fileroot'] ??= fileroot();
    $GLOBALS['date_display_format'] ??= 0;
    // Bypass database-dependent translation lookups so xl() returns the
    // original string and xlt()/xla() apply only escaping.
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
 * Load and decode the JSON parameter file into an array of template variables.
 *
 * @return array<string, mixed>
 */
function loadParameters(?string $path): array
{
    if ($path === null) {
        return [];
    }

    $json = file_get_contents($path);
    if ($json === false) {
        fwrite(STDERR, "Cannot read parameter file: {$path}\n");
        exit(1);
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "Parameter file is not a JSON object: {$path}\n");
        exit(1);
    }

    /** @var array<string, mixed> $decoded */
    return $decoded;
}

$template = $argv[1] ?? null;
if ($template === null) {
    fwrite(STDERR, "Usage: php tools/preview/render.php <template> [params.json] > out.html\n");
    exit(1);
}

$parameters = loadParameters($argv[2] ?? null);

try {
    echo previewEnvironment()->render($template, $parameters);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Render failed: ' . $e->getMessage() . "\n");
    exit(1);
}
