<?php

/**
 * WCAG 2.1 contrast outcome for one foreground/background pairing.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\DesignSystem;

enum ContrastRating: string
{
    /** Meets 1.4.6 Enhanced (7:1 normal text, 4.5:1 large). */
    case Aaa = 'AAA';

    /** Meets 1.4.3 Minimum (4.5:1 normal text, 3:1 large). */
    case Aa = 'AA';

    /** Below the applicable minimum. */
    case Fail = 'Fail';

    /**
     * Rate a measured ratio against the thresholds for the text size in use.
     *
     * "Large" is WCAG's definition: at least 18pt, or 14pt bold.
     */
    public static function forRatio(float $ratio, bool $largeText = false): self
    {
        $minimum = $largeText ? 3.0 : 4.5;
        $enhanced = $largeText ? 4.5 : 7.0;

        return match (true) {
            $ratio >= $enhanced => self::Aaa,
            $ratio >= $minimum => self::Aa,
            default => self::Fail,
        };
    }

    /** Whether the pairing may be shipped — AA is the project's bar. */
    public function passes(): bool
    {
        return $this !== self::Fail;
    }

    /** Status-badge variant naming this outcome on the style guide. */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Aaa => 'success',
            self::Aa => 'info',
            self::Fail => 'danger',
        };
    }
}
