<?php

/**
 * One patient's display identity for the UDS report drill-down: a formatted
 * name and date of birth, enough to recognise the patient and open their
 * Snapshot.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Reporting\Drilldown;

final readonly class PatientDirectoryEntry
{
    public function __construct(
        public int $pid,
        public string $name,
        public ?string $dateOfBirth,
    ) {
    }
}
