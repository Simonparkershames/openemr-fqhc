<?php

/**
 * Keeps the icon vocabulary honest across the PHP/JS boundary.
 *
 * The `Icon` enum is what server-side code is allowed to ask for; the registry
 * in `fqhc-icons.js` is what the browser can actually draw. Nothing at runtime
 * connects them — a template passes a string — so a rename on either side is
 * silent: PHP would emit a name that renders nothing, or the browser would
 * carry a drawing no one can reach. These tests are that connection.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\FQHC\DesignSystem;

use OpenEMR\FQHC\DesignSystem\Icon;
use PHPUnit\Framework\TestCase;

final class IconRegistryTest extends TestCase
{
    private const MODULE_REL = '/interface/modules/custom_modules/oe-module-fqhc';
    private const ICONS_JS = self::MODULE_REL . '/public/assets/js/fqhc-icons.js';
    private const TEMPLATE_DIR = self::MODULE_REL . '/templates/fqhc';

    /** The status-badge variants, each of which leads with an icon of the same name. */
    private const BADGE_VARIANTS = ['success', 'warning', 'danger', 'info', 'neutral'];

    public function testEnumAndScriptRegistryDeclareExactlyTheSameNames(): void
    {
        $inPhp = array_map(static fn(Icon $icon): string => $icon->value, Icon::cases());
        $inJs = $this->scriptIconNames();

        sort($inPhp);
        sort($inJs);

        self::assertSame(
            $inPhp,
            $inJs,
            'Icon enum and fqhc-icons.js must declare the same names. '
            . 'A name in only one of them renders nothing, or is undocumented.',
        );
    }

    public function testEveryIconHasPathDataAndAViewBox(): void
    {
        $script = $this->scriptSource();

        foreach (Icon::cases() as $icon) {
            self::assertMatchesRegularExpression(
                '/"' . preg_quote($icon->value, '/') . '": \{ fa: "[a-z0-9-]+", viewBox: "[0-9 ]+", d: "[^"]+" \}/',
                $script,
                "Icon {$icon->value} must carry an upstream name, a viewBox, and path data.",
            );
        }
    }

    public function testEveryBadgeVariantHasAnIconOfTheSameName(): void
    {
        // fqhc-status-badge maps a variant to its leading icon by name, so a
        // variant without a matching icon silently renders an empty element.
        // Checked against the browser-side registry rather than the enum: the
        // registry is what actually has to hold a drawing, and the enum is
        // pinned to it by testEnumAndScriptRegistryDeclareExactlyTheSameNames.
        $drawable = $this->scriptIconNames();

        foreach (self::BADGE_VARIANTS as $variant) {
            self::assertContains(
                $variant,
                $drawable,
                "Status-badge variant '{$variant}' needs an icon named '{$variant}'.",
            );
        }
    }

    public function testTemplatesOnlyReferenceKnownIconNames(): void
    {
        $known = array_map(static fn(Icon $icon): string => $icon->value, Icon::cases());
        $unknown = [];

        foreach ($this->templateFiles() as $path) {
            $source = (string) file_get_contents($path);

            // Literal values only. A Twig expression resolves at render time
            // from the enum and cannot name something that does not exist.
            // `name=` is read only inside an <fqhc-icon> tag — form controls
            // all over these templates carry an unrelated `name` attribute.
            preg_match_all('/\bicon="([a-z][a-z0-9-]*)"/', $source, $iconAttributes);
            preg_match_all('/<fqhc-icon\b[^>]*\bname="([a-z][a-z0-9-]*)"/', $source, $iconElements);

            foreach ([...$iconAttributes[1], ...$iconElements[1]] as $name) {
                if (!in_array($name, $known, true)) {
                    $unknown[] = basename($path) . ': ' . $name;
                }
            }
        }

        // The style guide deliberately renders one unknown name to demonstrate
        // that a miss draws nothing; every other occurrence is a typo.
        $unknown = array_values(array_filter(
            $unknown,
            static fn(string $entry): bool => $entry !== 'showcase.html.twig: no-such-icon',
        ));

        self::assertSame([], $unknown, 'Templates reference icon names the design system does not define.');
    }

    public function testScriptRegistersTheIconElement(): void
    {
        self::assertStringContainsString(
            "customElements.define('fqhc-icon'",
            $this->scriptSource(),
            'Web Component <fqhc-icon> must be registered.',
        );
    }

    public function testIconsAreDecorativeUnlessGivenALabel(): void
    {
        // Every icon in this system sits beside text that already carries the
        // meaning, so hiding it from assistive tech is the correct default.
        $script = $this->scriptSource();

        self::assertStringContainsString('aria-hidden="true" focusable="false"', $script);
        self::assertStringContainsString('role="img" aria-label=', $script);
    }

    /**
     * @return list<string>
     */
    private function scriptIconNames(): array
    {
        preg_match_all('/^    "([a-z][a-z0-9-]*)": \{ fa:/m', $this->scriptSource(), $matches);

        return $matches[1];
    }

    private function scriptSource(): string
    {
        return (string) file_get_contents($this->repoRoot() . self::ICONS_JS);
    }

    /**
     * @return list<string>
     */
    private function templateFiles(): array
    {
        $found = glob($this->repoRoot() . self::TEMPLATE_DIR . '/*.twig');

        self::assertIsArray($found);
        self::assertNotEmpty($found, 'Expected FQHC templates to scan.');

        return $found;
    }

    private function repoRoot(): string
    {
        return dirname(__DIR__, 5);
    }
}
