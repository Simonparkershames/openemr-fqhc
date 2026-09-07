<?php

/**
 * Builds the front-desk day view from typed schedule rows (issue #36).
 *
 * Pure and deterministic: phase classification, arrival-readiness flags,
 * display formatting, and time ordering all happen here so they are
 * testable without a database. The SQL boundary stays in
 * FrontDeskScheduleRepository.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\FrontDesk;

use DateTimeImmutable;

final readonly class FrontDeskDayBuilder
{
    private AppointmentStatusClassifier $classifier;

    public function __construct(?AppointmentStatusClassifier $classifier = null)
    {
        $this->classifier = $classifier ?? new AppointmentStatusClassifier();
    }

    /**
     * @param list<ScheduleRow> $rows
     */
    public function build(string $date, array $rows): FrontDeskDay
    {
        $sorted = $rows;
        usort(
            $sorted,
            static fn(ScheduleRow $a, ScheduleRow $b): int => [$a->startTime, $a->eventId] <=> [$b->startTime, $b->eventId],
        );

        return new FrontDeskDay(
            $date,
            array_map($this->toAppointment(...), $sorted),
        );
    }

    private function toAppointment(ScheduleRow $row): FrontDeskAppointment
    {
        return new FrontDeskAppointment(
            $row->eventId,
            $row->pid,
            $this->formatName($row),
            $this->formatTime($row->startTime),
            $row->startTime,
            intdiv($row->durationSeconds, 60),
            $row->providerId,
            $row->providerName,
            $row->categoryName,
            $row->statusTitle,
            $this->classifier->classify($row->statusCode),
            $this->readinessFlags($row),
        );
    }

    private function formatName(ScheduleRow $row): string
    {
        $last = trim($row->lastName);
        $first = trim($row->firstName);
        if ($last === '' && $first === '') {
            return 'Patient #' . $row->pid;
        }
        if ($last === '' || $first === '') {
            return $last . $first;
        }

        return $last . ', ' . $first;
    }

    private function formatTime(string $startTime): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!H:i:s', $startTime);
        if ($parsed === false) {
            $parsed = DateTimeImmutable::createFromFormat('!H:i', $startTime);
        }

        return $parsed === false ? $startTime : $parsed->format('g:i A');
    }

    /**
     * @return list<ReadinessFlag>
     */
    private function readinessFlags(ScheduleRow $row): array
    {
        $flags = [];

        $dob = trim((string) $row->dateOfBirth);
        if ($dob === '' || str_starts_with($dob, '0000-00-00')) {
            $flags[] = ReadinessFlag::MissingDateOfBirth;
        }

        if (trim((string) $row->sexCode) === '') {
            $flags[] = ReadinessFlag::MissingSex;
        }

        if (!$row->hasInsuranceOnFile) {
            $flags[] = ReadinessFlag::NoInsuranceOnFile;
        }

        if (!$row->hasIncomeDetermination) {
            $flags[] = ReadinessFlag::NoIncomeDetermination;
        }

        return $flags;
    }
}
