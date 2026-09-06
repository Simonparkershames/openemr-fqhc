<?php

/**
 * Resolves patient display identities for the UDS report drill-down in a single
 * query.
 *
 * The boundary that turns the roster's bare patient ids into names and dates of
 * birth: it reads `patient_data` for the whole referenced id set at once, so the
 * drill-down never issues a query per patient. All name formatting stays here at
 * the edge; the presenter that consumes the directory stays pure.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Reporting\Drilldown;

use OpenEMR\Common\Database\QueryUtils;

final class PatientDirectoryRepository
{
    /**
     * @param list<int> $pids
     */
    public function findByPids(array $pids): PatientDirectory
    {
        $pids = array_values(array_unique(array_filter($pids, static fn(int $pid): bool => $pid > 0)));
        if ($pids === []) {
            return new PatientDirectory();
        }

        $placeholders = implode(', ', array_fill(0, count($pids), '?'));
        $rows = QueryUtils::fetchRecords(
            'SELECT pid, fname, lname, DOB FROM patient_data WHERE pid IN (' . $placeholders . ')',
            $pids,
        );

        $entries = [];
        foreach ($rows as $row) {
            $pid = is_numeric($row['pid'] ?? null) ? (int) $row['pid'] : 0;
            if ($pid <= 0) {
                continue;
            }
            $entries[$pid] = new PatientDirectoryEntry(
                pid: $pid,
                name: $this->formatName($this->stringField($row, 'lname'), $this->stringField($row, 'fname'), $pid),
                dateOfBirth: $this->stringField($row, 'DOB'),
            );
        }

        return new PatientDirectory($entries);
    }

    private function formatName(?string $last, ?string $first, int $pid): string
    {
        if ($last !== null && $first !== null) {
            return $last . ', ' . $first;
        }

        $single = $last ?? $first;

        return $single ?? ('Patient #' . $pid);
    }

    /**
     * @param array<mixed> $row
     */
    private function stringField(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
