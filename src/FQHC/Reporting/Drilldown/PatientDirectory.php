<?php

/**
 * A lookup of patient display identities for the UDS report drill-down.
 *
 * Resolved once at the boundary for the set of patient ids a report references,
 * then handed to the pure presenter so the drill-down can show names without
 * the presenter touching the database. A missing id yields null — the presenter
 * falls back to the id so a name gap never hides a patient from the worklist.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Reporting\Drilldown;

final readonly class PatientDirectory
{
    /**
     * @param array<int, PatientDirectoryEntry> $entries keyed by patient id
     */
    public function __construct(private array $entries = [])
    {
    }

    public function find(int $pid): ?PatientDirectoryEntry
    {
        return $this->entries[$pid] ?? null;
    }
}
