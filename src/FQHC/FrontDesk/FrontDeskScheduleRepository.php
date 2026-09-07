<?php

/**
 * Database boundary for the front-desk day view (issue #36).
 *
 * The day's appointments come from the certified calendar code
 * (library/appointments.inc.php fetchAppointments()), which already expands
 * recurring events and applies the calendar's own filters — the entry point
 * calls it and hands the raw rows here. This class parses those rows into
 * typed values and enriches them with the readiness inputs: patient sex
 * (batched), the site's `apptstat` labels (batched), and the existing FQHC
 * insurance/income repositories. Classification and formatting stay in the
 * pure day builder this feeds.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\FrontDesk;

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\FQHC\Income\PatientIncomeRepository;
use OpenEMR\FQHC\Payer\PatientPayerRepository;

final readonly class FrontDeskScheduleRepository
{
    private PatientPayerRepository $payerRepository;
    private PatientIncomeRepository $incomeRepository;

    public function __construct(
        ?PatientPayerRepository $payerRepository = null,
        ?PatientIncomeRepository $incomeRepository = null,
    ) {
        $this->payerRepository = $payerRepository ?? new PatientPayerRepository();
        $this->incomeRepository = $incomeRepository ?? new PatientIncomeRepository();
    }

    /**
     * @param array<mixed> $appointments raw rows from fetchAppointments()
     * @return list<ScheduleRow>
     */
    public function rowsFromAppointments(array $appointments): array
    {
        $records = [];
        $pids = [];
        foreach ($appointments as $record) {
            if (!is_array($record)) {
                continue;
            }
            $pid = $record['pid'] ?? null;
            if (!is_numeric($pid) || (int) $pid <= 0) {
                continue;
            }
            $records[] = $record;
            $pids[] = (int) $pid;
        }

        $pids = array_values(array_unique($pids));
        $sexByPid = $this->sexByPid($pids);
        $statusTitles = $this->statusTitles();
        $insuranceByPid = [];
        $incomeByPid = [];
        foreach ($pids as $pid) {
            $insuranceByPid[$pid] = $this->payerRepository->findPrimaryByPid($pid) !== null;
            $incomeByPid[$pid] = $this->incomeRepository->findByPid($pid) !== null;
        }

        $rows = [];
        foreach ($records as $record) {
            $eventId = $record['pc_eid'] ?? null;
            if (!is_numeric($eventId) || (int) $eventId <= 0) {
                continue;
            }
            $pidRaw = $record['pid'] ?? null;
            if (!is_numeric($pidRaw)) {
                continue;
            }
            $pid = (int) $pidRaw;

            $providerName = trim(
                $this->stringOrEmpty($record['ufname'] ?? null)
                . ' '
                . $this->stringOrEmpty($record['ulname'] ?? null)
            );
            $providerIdRaw = $record['pc_aid'] ?? null;
            $providerId = is_numeric($providerIdRaw) ? (int) $providerIdRaw : 0;
            $statusCode = $this->stringOrEmpty($record['pc_apptstatus'] ?? null);
            $duration = $record['pc_duration'] ?? null;

            $rows[] = new ScheduleRow(
                (int) $eventId,
                $pid,
                $this->stringOrEmpty($record['fname'] ?? null),
                $this->stringOrEmpty($record['lname'] ?? null),
                $sexByPid[$pid] ?? null,
                $this->stringOrNull($record['DOB'] ?? null),
                $this->stringOrEmpty($record['pc_startTime'] ?? null),
                is_numeric($duration) ? (int) $duration : 0,
                $providerId,
                $providerName,
                $this->stringOrEmpty($record['pc_catname'] ?? null),
                $statusCode,
                $statusTitles[$statusCode] ?? $statusCode,
                $insuranceByPid[$pid] ?? false,
                $incomeByPid[$pid] ?? false,
            );
        }

        return $rows;
    }

    /**
     * @param list<int> $pids
     * @return array<int, ?string>
     */
    private function sexByPid(array $pids): array
    {
        if ($pids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($pids), '?'));
        $records = QueryUtils::fetchRecords(
            "SELECT pid, sex FROM patient_data WHERE pid IN ($placeholders)",
            $pids,
        );

        $map = [];
        foreach ($records as $record) {
            $pid = $record['pid'] ?? null;
            if (is_numeric($pid)) {
                $map[(int) $pid] = $this->stringOrNull($record['sex'] ?? null);
            }
        }

        return $map;
    }

    /**
     * The site's `apptstat` labels, keyed by status code.
     *
     * @return array<string, string>
     */
    private function statusTitles(): array
    {
        $records = QueryUtils::fetchRecords(
            "SELECT option_id, title FROM list_options WHERE list_id = 'apptstat'",
        );

        $map = [];
        foreach ($records as $record) {
            $code = $record['option_id'] ?? null;
            $title = $record['title'] ?? null;
            if (is_string($code) && is_string($title) && $title !== '') {
                $map[$code] = $title;
            }
        }

        return $map;
    }

    private function stringOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
