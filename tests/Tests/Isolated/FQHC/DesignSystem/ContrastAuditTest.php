<?php

/**
 * Tests for the palette contrast audit — including the guard that matters
 * most: the palette the module actually ships must meet WCAG 2.1 AA.
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
use OpenEMR\FQHC\DesignSystem\ContrastRating;
use OpenEMR\FQHC\DesignSystem\TokenSheetParser;
use PHPUnit\Framework\TestCase;

final class ContrastAuditTest extends TestCase
{
    private const TOKENS_CSS =
        '/interface/modules/custom_modules/oe-module-fqhc/public/assets/css/tokens.css';

    /**
     * The shipped palette must be accessible. This is the assertion the style
     * guide makes visible; keeping it as a test means a token edit that breaks
     * a pairing fails CI instead of waiting to be noticed on the page.
     */
    public function testShippedPaletteMeetsWcagAa(): void
    {
        $audit = new ContrastAudit();
        $pairs = $audit->measure($this->shippedTokenValues());

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

        self::assertSame([], $failures, "Palette pairings below WCAG 2.1 AA:\n" . implode("\n", $failures));
    }

    public function testEveryDeclaredPairingIsMeasurableAgainstTheShippedPalette(): void
    {
        // A pairing naming a token that no longer exists is silently skipped at
        // render time, which would quietly shrink the audit. Pin the count so a
        // renamed token surfaces here rather than as a shorter table.
        $pairs = (new ContrastAudit())->measure($this->shippedTokenValues());

        self::assertCount(22, $pairs);
    }

    public function testEveryMeasuredPairCarriesItsTokenValues(): void
    {
        $pairs = (new ContrastAudit())->measure($this->shippedTokenValues());

        foreach ($pairs as $pair) {
            self::assertNotSame('', $pair->usage);
            self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $pair->foregroundValue);
            self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $pair->backgroundValue);
            self::assertGreaterThanOrEqual(1.0, $pair->ratio);
        }
    }

    public function testPairingsNamingAMissingTokenAreSkipped(): void
    {
        $pairs = (new ContrastAudit())->measure([
            '--fqhc-text' => '#0f172a',
            '--fqhc-surface-card' => '#ffffff',
        ]);

        self::assertCount(1, $pairs);
        self::assertSame('Body text on a card', $pairs[0]->usage);
    }

    public function testPairingsWithAnUnparseableValueAreSkipped(): void
    {
        $pairs = (new ContrastAudit())->measure([
            '--fqhc-text' => 'currentColor',
            '--fqhc-surface-card' => '#ffffff',
        ]);

        self::assertSame([], $pairs);
    }

    public function testFailuresSelectsOnlyTheFailingPairs(): void
    {
        $audit = new ContrastAudit();
        $pairs = $audit->measure([
            '--fqhc-text' => '#cccccc',      // 1.61:1 on white — fails
            '--fqhc-surface-card' => '#ffffff',
            '--fqhc-color-accent' => '#4f46e5', // 6.28:1 on white — passes
        ]);

        $failures = $audit->failures($pairs);

        self::assertCount(1, $failures);
        self::assertSame(ContrastRating::Fail, $failures[0]->rating);
        self::assertSame('--fqhc-text', $failures[0]->foregroundName);
    }

    /**
     * @return array<string, string>
     */
    private function shippedTokenValues(): array
    {
        return (new TokenSheetParser())
            ->parseFile(dirname(__DIR__, 5) . self::TOKENS_CSS)
            ->values();
    }
}
