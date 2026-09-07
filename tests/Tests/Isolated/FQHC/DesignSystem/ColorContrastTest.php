<?php

/**
 * Unit tests for the WCAG 2.1 contrast arithmetic.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\FQHC\DesignSystem;

use OpenEMR\FQHC\DesignSystem\ColorContrast;
use OpenEMR\FQHC\DesignSystem\ContrastRating;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ColorContrastTest extends TestCase
{
    public function testBlackOnWhiteIsTheMaximumRatio(): void
    {
        self::assertEqualsWithDelta(21.0, (new ColorContrast())->ratio('#000000', '#ffffff'), 0.0001);
    }

    public function testAColorAgainstItselfIsOne(): void
    {
        self::assertEqualsWithDelta(1.0, (new ColorContrast())->ratio('#0f766e', '#0f766e'), 0.0001);
    }

    public function testOrderOfArgumentsDoesNotChangeTheRatio(): void
    {
        $contrast = new ColorContrast();

        self::assertSame(
            $contrast->ratio('#0f766e', '#ffffff'),
            $contrast->ratio('#ffffff', '#0f766e'),
        );
    }

    public function testShorthandHexExpandsToTheSameColor(): void
    {
        $contrast = new ColorContrast();

        self::assertSame($contrast->ratio('#fff', '#000'), $contrast->ratio('#ffffff', '#000000'));
    }

    public function testLeadingHashIsOptional(): void
    {
        $contrast = new ColorContrast();

        self::assertSame($contrast->ratio('ffffff', '000000'), $contrast->ratio('#ffffff', '#000000'));
    }

    public function testAlphaChannelIsIgnored(): void
    {
        $contrast = new ColorContrast();

        self::assertSame($contrast->ratio('#0f766e80', '#ffffff'), $contrast->ratio('#0f766e', '#ffffff'));
    }

    public function testReportedRatioTruncatesRatherThanRoundsUp(): void
    {
        // #767676 on white is 4.5406…; truncation must not turn a near-miss
        // into a passing 4.5 anywhere else in the palette.
        $contrast = new ColorContrast();

        self::assertSame(4.54, $contrast->reportedRatio('#767676', '#ffffff'));
    }

    public function testWhiteHasFullRelativeLuminance(): void
    {
        self::assertEqualsWithDelta(1.0, (new ColorContrast())->relativeLuminance('#ffffff'), 0.0001);
    }

    public function testBlackHasZeroRelativeLuminance(): void
    {
        self::assertEqualsWithDelta(0.0, (new ColorContrast())->relativeLuminance('#000000'), 0.0001);
    }

    #[DataProvider('invalidColorProvider')]
    public function testUnparseableColorsThrow(string $color): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ColorContrast())->relativeLuminance($color);
    }

    /**
     * @return array<string, array{string}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function invalidColorProvider(): array
    {
        return [
            'named colour' => ['rebeccapurple'],
            'functional notation' => ['rgb(15, 118, 110)'],
            'wrong digit count' => ['#12345'],
            'not hex digits' => ['#gggggg'],
            'empty' => [''],
        ];
    }

    #[DataProvider('ratingProvider')]
    public function testRatingsUseTheThresholdsForTheTextSize(float $ratio, bool $large, ContrastRating $expected): void
    {
        self::assertSame($expected, ContrastRating::forRatio($ratio, $large));
    }

    /**
     * @return array<string, array{float, bool, ContrastRating}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function ratingProvider(): array
    {
        return [
            'normal text just below AA' => [4.49, false, ContrastRating::Fail],
            'normal text exactly AA' => [4.5, false, ContrastRating::Aa],
            'normal text just below AAA' => [6.99, false, ContrastRating::Aa],
            'normal text exactly AAA' => [7.0, false, ContrastRating::Aaa],
            'large text just below AA' => [2.99, true, ContrastRating::Fail],
            'large text exactly AA' => [3.0, true, ContrastRating::Aa],
            'large text exactly AAA' => [4.5, true, ContrastRating::Aaa],
        ];
    }

    public function testOnlyFailingRatingsAreRejected(): void
    {
        self::assertTrue(ContrastRating::Aaa->passes());
        self::assertTrue(ContrastRating::Aa->passes());
        self::assertFalse(ContrastRating::Fail->passes());
    }

    public function testEachRatingHasItsOwnBadgeVariant(): void
    {
        self::assertSame('success', ContrastRating::Aaa->badgeVariant());
        self::assertSame('info', ContrastRating::Aa->badgeVariant());
        self::assertSame('danger', ContrastRating::Fail->badgeVariant());
    }
}
