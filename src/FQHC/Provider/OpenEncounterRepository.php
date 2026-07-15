<?php

/**
 * Database boundary for the provider's open encounters (issue #38): the
 * visits opened today for which this provider is the responsible clinician.
 * Read-only; the note itself is written on the certified encounter screen the
 * workspace links to.
 *
 * "Open" is scoped to today's encounters — the visits in flight during the
 * clinic session. A closed/checked-out visit still has its encounter row, so
 * a stricter signed/unsigned flag would need the esign feature; scoping to
 * the current day keeps the list to what the provider is actively working
 * without depending on optional modules.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Provider;

use OpenEMR\Common\Database\QueryUtils;

final readonly class OpenEncounterRepository
{
    /**
     * The provider's encounters opened on the given day, most recent first.
     *
     * @return list<OpenEncounter>
     */
    public function openForProviderOnDate(int $providerId, string $date): array
    {
        if ($providerId <= 0) {
            return [];
        }

        $records = QueryUtils::fetchRecords(
            'SELECT fe.encounter, fe.pid, fe.date, fe.reason, '
            . "pd.fname, pd.lname "
            . 'FROM form_encounter fe '
            . 'JOIN patient_data pd ON pd.pid = fe.pid '
            . 'WHERE fe.provider_id = ? AND DATE(fe.date) = ? '
            . 'ORDER BY fe.date DESC, fe.encounter DESC',
            [$providerId, $date],
        );

        $encounters = [];
        foreach ($records as $record) {
            $encounterId = $record['encounter'] ?? null;
            $pid = $record['pid'] ?? null;
            if (!is_numeric($encounterId) || !is_numeric($pid)) {
                continue;
            }

            $encounters[] = new OpenEncounter(
                (int) $encounterId,
                (int) $pid,
                $this->formatName($record['lname'] ?? null, $record['fname'] ?? null, (int) $pid),
                $this->formatTime($record['date'] ?? null),
                $this->stringOrEmpty($record['reason'] ?? null),
            );
        }

        return $encounters;
    }

    private function formatName(mixed $last, mixed $first, int $pid): string
    {
        $last = is_string($last) ? trim($last) : '';
        $first = is_string($first) ? trim($first) : '';
        if ($last === '' && $first === '') {
            return 'Patient #' . $pid;
        }
        if ($last === '' || $first === '') {
            return $last . $first;
        }

        return $last . ', ' . $first;
    }

    private function formatTime(mixed $datetime): string
    {
        if (!is_string($datetime) || $datetime === '') {
            return '';
        }
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return '';
        }

        return date('g:i A', $timestamp);
    }

    private function stringOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
