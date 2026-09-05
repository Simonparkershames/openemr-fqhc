<?php

/**
 * How a design token should be demonstrated in the style guide.
 *
 * The style guide cannot render every token the same way — a colour needs a
 * swatch, a font size needs a specimen at that size, a spacing step needs a
 * bar drawn to that width. This enum names the presentation each token gets,
 * so the decision lives in one typed place rather than in template
 * conditionals keyed off substrings of the token name.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\DesignSystem;

enum TokenKind: string
{
    /** A colour: rendered as a swatch with its literal value. */
    case Color = 'color';

    /** A font stack: rendered as a specimen in that family. */
    case FontFamily = 'font-family';

    /** A font size: rendered as a specimen at that size. */
    case FontSize = 'font-size';

    /** A font weight: rendered as a specimen at that weight. */
    case FontWeight = 'font-weight';

    /** A spacing step: rendered as a bar of that width. */
    case Space = 'space';

    /** A corner radius: rendered as a box with that radius. */
    case Radius = 'radius';

    /** A box-shadow (elevation or focus ring): rendered on a raised tile. */
    case Shadow = 'shadow';

    /** A duration or transition: rendered as its literal value. */
    case Motion = 'motion';

    /** Anything else: rendered as its literal value only. */
    case Raw = 'raw';

    /**
     * Infer the presentation for a token from its name and value.
     *
     * Name wins over value because the names are deliberate and stable
     * (`--fqhc-space-4`, `--fqhc-shadow-md`); the value fallback only has to
     * catch colours written in a form no naming convention covers.
     */
    public static function infer(string $name, string $value): self
    {
        return match (true) {
            str_contains($name, 'shadow'), str_contains($name, 'focus-ring') => self::Shadow,
            str_contains($name, 'font-sans'), str_contains($name, 'font-mono') => self::FontFamily,
            str_contains($name, 'font-size') => self::FontSize,
            str_contains($name, 'font-weight') => self::FontWeight,
            str_contains($name, 'space') => self::Space,
            str_contains($name, 'radius') => self::Radius,
            str_contains($name, 'transition'), str_contains($name, 'duration') => self::Motion,
            self::looksLikeColor($value) => self::Color,
            default => self::Raw,
        };
    }

    private static function looksLikeColor(string $value): bool
    {
        return preg_match('/^(#[0-9a-f]{3,8}|(rgb|hsl)a?\()/i', $value) === 1;
    }
}
