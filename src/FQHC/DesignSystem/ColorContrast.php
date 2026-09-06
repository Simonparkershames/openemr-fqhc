<?php

/**
 * WCAG 2.1 contrast-ratio arithmetic for sRGB hex colours.
 *
 * Implements the relative-luminance and contrast-ratio definitions from WCAG
 * 2.1 so the style guide can *measure* the palette rather than assert that it
 * is accessible. Pure and dependency-free: no palette knowledge, no I/O.
 *
 * @see https://www.w3.org/TR/WCAG21/#dfn-relative-luminance
 * @see https://www.w3.org/TR/WCAG21/#dfn-contrast-ratio
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\DesignSystem;

final readonly class ColorContrast
{
    /**
     * Contrast ratio between two colours, in the range 1.0–21.0.
     *
     * Order does not matter; the lighter colour is always the numerator.
     *
     * @throws \InvalidArgumentException when either colour is not a
     *         three-, six-, or eight-digit sRGB hex value.
     */
    public function ratio(string $foreground, string $background): float
    {
        $lighter = $this->relativeLuminance($foreground);
        $darker = $this->relativeLuminance($background);
        if ($lighter < $darker) {
            [$lighter, $darker] = [$darker, $lighter];
        }

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Contrast ratio rounded the way it is conventionally reported — two
     * decimals, truncated rather than rounded up, so a 4.4996 never reads as
     * a passing "4.50".
     */
    public function reportedRatio(string $foreground, string $background): float
    {
        return floor($this->ratio($foreground, $background) * 100) / 100;
    }

    /**
     * Relative luminance of an sRGB colour, per WCAG 2.1.
     *
     * @throws \InvalidArgumentException when the colour cannot be parsed.
     */
    public function relativeLuminance(string $color): float
    {
        [$red, $green, $blue] = $this->toRgb($color);

        return 0.2126 * $this->linearize($red)
            + 0.7152 * $this->linearize($green)
            + 0.0722 * $this->linearize($blue);
    }

    /**
     * Parse an sRGB hex colour into 0–255 channels. An eight-digit value's
     * alpha channel is ignored: WCAG contrast is defined for composited
     * colours, and the tokens that carry alpha are shadows, not text.
     *
     * @return array{int, int, int}
     */
    private function toRgb(string $color): array
    {
        $hex = ltrim(trim($color), '#');
        if (preg_match('/^[0-9a-fA-F]{3}$/', $hex) === 1) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (preg_match('/^[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/', $hex) !== 1) {
            throw new \InvalidArgumentException('Not an sRGB hex colour.');
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /** Convert one 0–255 sRGB channel to its linear-light value. */
    private function linearize(int $channel): float
    {
        $normalized = $channel / 255;

        return $normalized <= 0.04045
            ? $normalized / 12.92
            : (($normalized + 0.055) / 1.055) ** 2.4;
    }
}
