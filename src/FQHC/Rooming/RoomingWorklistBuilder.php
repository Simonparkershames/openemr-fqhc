<?php

/**
 * Builds the MA rooming worklist from the front-desk day view (issue #37).
 *
 * Pure and deterministic: it partitions the day's appointments by the same
 * phase classification the front-desk workspace uses — Arrived patients are
 * waiting to be roomed, WithCareTeam patients are roomed — and attaches the
 * rooming context (room label, open encounter) and clinical glance supplied
 * by the repositories. Every other phase is off the worklist: expected
 * patients haven't checked in, and checked-out/cancelled patients are done.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Rooming;

use OpenEMR\FQHC\FrontDesk\AppointmentPhase;
use OpenEMR\FQHC\FrontDesk\FrontDeskDay;

final readonly class RoomingWorklistBuilder
{
    /**
     * @param array<int, string> $roomLabelByEventId
     * @param array<int, int> $encounterByEventId
     * @param array<int, PatientGlance> $glanceByPid
     */
    public function build(
        FrontDeskDay $day,
        array $roomLabelByEventId,
        array $encounterByEventId,
        array $glanceByPid,
    ): RoomingWorklist {
        $awaitingRooming = [];
        $withCareTeam = [];

        foreach ($day->appointments as $appointment) {
            $entry = new RoomingQueueEntry(
                $appointment,
                $roomLabelByEventId[$appointment->eventId] ?? null,
                $encounterByEventId[$appointment->eventId] ?? null,
                $glanceByPid[$appointment->pid] ?? PatientGlance::empty(),
            );

            if ($appointment->phase === AppointmentPhase::Arrived) {
                $awaitingRooming[] = $entry;
            } elseif ($appointment->phase === AppointmentPhase::WithCareTeam) {
                $withCareTeam[] = $entry;
            }
        }

        return new RoomingWorklist($day->date, $awaitingRooming, $withCareTeam);
    }
}
