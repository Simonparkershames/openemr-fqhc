<?php

/**
 * One measured foreground/background pairing from the palette.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\DesignSystem;

final readonly class ContrastPair
{
    /**
     * @param string $usage          Where in the UI the pairing appears.
     * @param string $foregroundName Token name of the foreground colour.
     * @param string $backgroundName Token name of the background colour.
     * @param float  $ratio          Measured contrast ratio, as reported.
     * @param bool   $largeText      Whether the WCAG large-text thresholds apply.
     */
    public function __construct(
        public string $usage,
        public string $foregroundName,
        public string $foregroundValue,
        public string $backgroundName,
        public string $backgroundValue,
        public float $ratio,
        public ContrastRating $rating,
        public bool $largeText,
    ) {
    }
}
