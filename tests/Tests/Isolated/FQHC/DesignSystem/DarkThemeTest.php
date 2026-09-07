<?php

/**
 * Guards for the dark theme (issue #61).
 *
 * The dark palette is the half of the design system nobody looks at while
 * working in the other one, so every property it has to hold is asserted here:
 * that it clears WCAG AA, that it redefines colour and nothing else, and that
 * its two declaration blocks — the OS-preference one and the explicit-attribute
 * one — never drift apart.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\FQHC\DesignSystem;

use OpenEMR\FQHC\DesignSystem\ContrastAudit;
use OpenEMR\FQHC\DesignSystem\ContrastPair;
use OpenEMR\FQHC\DesignSystem\DesignSystemAssets;
use OpenEMR\FQHC\DesignSystem\Theme;
use OpenEMR\FQHC\DesignSystem\TokenSheetParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DarkThemeTest extends TestCase
{
    private const TOKENS_CSS =
        '/interface/modules/custom_modules/oe-module-fqhc/public/assets/css/tokens.css';

    /** The block behind the OS preference, inside the prefers-color-scheme media query. */
    private const MEDIA_SELECTOR = ':root:not([data-fqhc-theme="light"])';

    /** The block behind an explicit choice, which must win in both directions. */
    private const ATTRIBUTE_SELECTOR = ':root[data-fqhc-theme="dark"]';

    #[DataProvider('themeProvider')]
    public function testEveryThemeMeetsWcagAa(Theme $theme): void
    {
        $audit = new ContrastAudit();
        $pairs = $audit->measure($this->paletteFor($theme));

        $failures = array_map(
            static fn(ContrastPair $pair): string => sprintf(
                '%s: %s on %s is %.2f:1',
                $pair->usage,
                $pair->foregroundName,
                $pair->backgroundName,
                $pair->ratio,
            ),
            $audit->failures($pairs),
        );

        self::assertSame(
            [],
            $failures,
            "Pairings below WCAG 2.1 AA in the {$theme->value} theme:\n" . implode("\n", $failures),
        );
    }

    #[DataProvider('themeProvider')]
    public function testEveryThemeMeasuresTheSamePairings(Theme $theme): void
    {
        // A theme that resolved fewer pairings would be passing partly by
        // omission — a token it failed to define would drop out of the audit.
        self::assertCount(22, (new ContrastAudit())->measure($this->paletteFor($theme)));
    }

    public function testBothDarkBlocksDeclareIdenticalValues(): void
    {
        $parser = new TokenSheetParser();
        $css = $this->tokensCss();

        $fromMedia = $parser->parseOverrides($css, self::MEDIA_SELECTOR);
        $fromAttribute = $parser->parseOverrides($css, self::ATTRIBUTE_SELECTOR);

        self::assertNotSame([], $fromMedia, 'The prefers-color-scheme dark block must exist.');
        self::assertSame(
            $fromMedia,
            $fromAttribute,
            'The OS-preference and explicit-attribute dark blocks must stay identical, '
            . 'or the toggle and the system preference render different palettes.',
        );
    }

    public function testDarkRedefinesOnlyColorCarryingTokens(): void
    {
        // Redefining a size or a duration per theme would mean the two themes
        // are different designs rather than one design in two palettes.
        $allowed = ['color', 'surface', 'border', 'text', 'shadow', 'focus-ring'];

        foreach (array_keys($this->darkOverrides()) as $name) {
            $short = str_replace('--fqhc-', '', $name);
            $matches = array_filter($allowed, static fn(string $group): bool => str_starts_with($short, $group));

            self::assertNotEmpty($matches, "Dark theme must not redefine the non-colour token {$name}.");
        }
    }

    public function testDarkOverridesEveryColorTokenTheBasePaletteDeclares(): void
    {
        // A colour left un-overridden keeps its light value on a dark ground,
        // which is exactly the sort of miss that is invisible until someone
        // switches themes on a page nobody re-checked.
        $base = (new TokenSheetParser())->parseFile($this->tokensPath())->values();
        $dark = $this->darkOverrides();

        $missing = [];
        foreach ($base as $name => $value) {
            $short = str_replace('--fqhc-', '', $name);
            $isColorToken = str_starts_with($short, 'color-')
                || str_starts_with($short, 'surface-')
                || str_starts_with($short, 'border')
                || str_starts_with($short, 'text')
                || str_starts_with($short, 'shadow-')
                || $short === 'focus-ring';

            if ($isColorToken && !array_key_exists($name, $dark)) {
                $missing[] = $name;
            }
        }

        self::assertSame([], $missing, 'These colour tokens keep their light value in dark: ' . implode(', ', $missing));
    }

    public function testThemeBootstrapAppliesBeforeAnyStylesheet(): void
    {
        // The snippet exists to beat the first paint; if it ever grew a
        // dependency on the DOM or on another script it would stop doing that.
        $script = DesignSystemAssets::themeBootstrapScript();

        self::assertStringContainsString(DesignSystemAssets::THEME_STORAGE_KEY, $script);
        self::assertStringContainsString(DesignSystemAssets::THEME_ATTRIBUTE, $script);
        self::assertStringContainsString('document.documentElement', $script);
        self::assertStringContainsString('try', $script, 'Storage access must be guarded.');
        self::assertStringNotContainsString('DOMContentLoaded', $script);
        self::assertStringNotContainsString('addEventListener', $script);
    }

    public function testEveryModulePageAppliesTheThemeBeforeItsStylesheets(): void
    {
        $publicDir = dirname(__DIR__, 5)
            . '/interface/modules/custom_modules/oe-module-fqhc/public';
        $pages = glob($publicDir . '/*.php');

        self::assertIsArray($pages);

        foreach ($pages as $page) {
            $source = (string) file_get_contents($page);
            if (!str_contains($source, 'Header::setupHeader')) {
                continue;
            }

            $bootstrap = strpos($source, 'themeBootstrapScript');
            $firstStylesheet = strpos($source, 'styleUrls');

            self::assertNotFalse(
                $bootstrap,
                basename($page) . ' renders a head but never applies the stored theme, so it flashes.',
            );
            self::assertNotFalse($firstStylesheet);
            self::assertLessThan(
                $firstStylesheet,
                $bootstrap,
                basename($page) . ' must apply the theme before its stylesheets load.',
            );
        }
    }

    public function testThemeScriptIsPartOfTheBundle(): void
    {
        self::assertContains('assets/js/fqhc-theme.js', DesignSystemAssets::SCRIPTS);
    }

    /**
     * @return array<string, array{Theme}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function themeProvider(): array
    {
        return [
            'light' => [Theme::Light],
            'dark' => [Theme::Dark],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function paletteFor(Theme $theme): array
    {
        $base = (new TokenSheetParser())->parseFile($this->tokensPath())->values();
        $selector = $theme->overrideSelector();
        $overrides = $selector === null
            ? []
            : (new TokenSheetParser())->parseOverrides($this->tokensCss(), $selector);

        return $theme->resolvePalette($base, $overrides);
    }

    /**
     * @return array<string, string>
     */
    private function darkOverrides(): array
    {
        return (new TokenSheetParser())->parseOverrides($this->tokensCss(), self::ATTRIBUTE_SELECTOR);
    }

    private function tokensCss(): string
    {
        return (string) file_get_contents($this->tokensPath());
    }

    private function tokensPath(): string
    {
        return dirname(__DIR__, 5) . self::TOKENS_CSS;
    }
}
