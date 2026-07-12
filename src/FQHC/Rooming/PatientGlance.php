<?php

/**
 * The at-a-glance clinical context an MA needs while rooming a patient
 * (issue #37): active allergies, active medications, and due screenings.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Rooming;

final readonly class PatientGlance
{
    /**
     * @param list<string> $allergies active allergy titles
     * @param list<string> $medications active medication titles
     * @param list<ScreeningDue> $screeningsDue
     */
    public function __construct(
        public array $allergies,
        public array $medications,
        public array $screeningsDue,
    ) {
    }

    public static function empty(): self
    {
        return new self([], [], []);
    }
}
