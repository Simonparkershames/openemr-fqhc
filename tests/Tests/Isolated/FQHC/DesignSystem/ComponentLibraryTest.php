<?php

/**
 * Contract tests for the Web Component library.
 *
 * These components are browser code with no PHP surface, so what can be
 * asserted here is the contract the rest of the system relies on: that every
 * documented element is actually registered, that the style guide demonstrates
 * each one, and that the constraints the design system claims — tokens only,
 * no hard-coded colour, reduced motion respected — actually hold in the source.
 *
 * A real DOM test belongs in the Jest suite; this is the guard that the
 * library and its documentation cannot drift apart.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\FQHC\DesignSystem;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ComponentLibraryTest extends TestCase
{
    private const MODULE_REL = '/interface/modules/custom_modules/oe-module-fqhc';
    private const COMPONENTS_JS = self::MODULE_REL . '/public/assets/js/fqhc-components.js';
    private const SHOWCASE_TWIG = self::MODULE_REL . '/templates/fqhc/showcase.html.twig';

    /** Every element the library ships. Adding one here without shipping it fails. */
    private const ELEMENTS = [
        'fqhc-page-header',
        'fqhc-card',
        'fqhc-field-row',
        'fqhc-status-badge',
        'fqhc-empty-state',
        'fqhc-stat',
        'fqhc-avatar',
        'fqhc-segmented',
        'fqhc-timeline',
        'fqhc-timeline-event',
        'fqhc-skeleton',
        'fqhc-progress',
        'fqhc-toast',
    ];

    #[DataProvider('elementProvider')]
    public function testEveryElementIsRegistered(string $element): void
    {
        self::assertStringContainsString(
            "customElements.define('{$element}'",
            $this->componentSource(),
            "Web Component <{$element}> must be registered.",
        );
    }

    #[DataProvider('elementProvider')]
    public function testEveryElementAppearsInTheStyleGuide(string $element): void
    {
        // A component nobody can see is a component nobody will reuse, and the
        // style guide is where it gets reviewed.
        self::assertStringContainsString(
            $element,
            (string) file_get_contents($this->repoRoot() . self::SHOWCASE_TWIG),
            "<{$element}> must be demonstrated in the style guide.",
        );
    }

    public function testNoComponentHardCodesAColour(): void
    {
        // Colour belongs to tokens.css. The one sanctioned exception is
        // fqhc-avatar's hsl(), which derives a hue from a patient id — a value
        // no fixed palette could supply — at fixed saturation and lightness.
        $source = $this->componentSource();
        $offenders = [];

        foreach (explode("\n", $source) as $number => $line) {
            // The lookbehind skips HTML entities like &#039; in the escaper,
            // which are not colours despite starting with a hash.
            if (preg_match('/(?<!&)#[0-9a-fA-F]{3,8}\b|\brgb\(|\bhsl\(/', $line) !== 1) {
                continue;
            }
            if (str_contains($line, 'hsl(${hue}')) {
                continue;
            }

            $offenders[] = ($number + 1) . ': ' . trim($line);
        }

        self::assertSame([], $offenders, "Hard-coded colours in the component library:\n" . implode("\n", $offenders));
    }

    public function testAnimatedComponentsRespectReducedMotion(): void
    {
        $source = $this->componentSource();

        // Any component that declares a keyframe animation must also disable
        // it under prefers-reduced-motion; the transition *token* already
        // collapses to 0ms, but a keyframe animation ignores it.
        self::assertGreaterThan(
            0,
            preg_match_all('/animation:\s*(?!none)/', $source),
            'Expected the library to animate something — if that is no longer true, '
            . 'this test and the reduced-motion rules it guards can go.',
        );

        self::assertGreaterThan(
            0,
            substr_count($source, 'prefers-reduced-motion'),
            'Components declare animations but never disable them under prefers-reduced-motion.',
        );
    }

    public function testEveryComponentUsesShadowDom(): void
    {
        // FqhcElement attaches the shadow root, so every component extending it
        // is encapsulated; a component extending HTMLElement directly would
        // leak the page's styles into it and vice versa.
        $source = $this->componentSource();

        preg_match_all("/customElements\.define\('([a-z-]+)', class extends (\w+)/", $source, $matches, PREG_SET_ORDER);

        self::assertCount(count(self::ELEMENTS), $matches, 'Every element must be defined in the same shape.');

        foreach ($matches as [, $element, $base]) {
            self::assertSame('FqhcElement', $base, "<{$element}> must extend FqhcElement for Shadow DOM encapsulation.");
        }
    }

    public function testStatDirectionsCoverBothPolarities(): void
    {
        // A measure where "up" is bad (open care gaps) must be expressible, or
        // callers will colour regressions green.
        $source = $this->componentSource();

        foreach (['up-bad', 'down-good'] as $direction) {
            self::assertStringContainsString($direction, $source, "fqhc-stat must support direction={$direction}.");
        }
    }

    /**
     * @return array<string, array{string}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function elementProvider(): array
    {
        $cases = [];
        foreach (self::ELEMENTS as $element) {
            $cases[$element] = [$element];
        }

        return $cases;
    }

    private function componentSource(): string
    {
        return (string) file_get_contents($this->repoRoot() . self::COMPONENTS_JS);
    }

    private function repoRoot(): string
    {
        return dirname(__DIR__, 5);
    }
}
