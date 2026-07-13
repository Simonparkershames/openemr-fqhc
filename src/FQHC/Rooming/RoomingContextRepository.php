<?php

/**
 * Database boundary for the rooming context (issue #37): which room each
 * appointment is in, which open encounter the tracker has mapped to it, and
 * the site's room list for the room picker. Read-only; the status/room
 * writes go through the certified manage_tracker_status() at the action
 * endpoint.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Rooming;

use OpenEMR\Common\Database\QueryUtils;

final readonly class RoomingContextRepository
{
    /**
     * Room labels keyed by event id for one day, resolved through the
     * site's `patient_flow_board_rooms` list (raw codes fall through).
     *
     * @param list<int> $eventIds
     * @return array<int, string>
     */
    public function roomLabelsByEventId(array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        $roomTitles = $this->roomTitlesByCode();
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $records = QueryUtils::fetchRecords(
            "SELECT pc_eid, pc_room FROM openemr_postcalendar_events WHERE pc_eid IN ($placeholders)",
            $eventIds,
        );

        $map = [];
        foreach ($records as $record) {
            $eid = $record['pc_eid'] ?? null;
            $room = $record['pc_room'] ?? null;
            if (is_numeric($eid) && is_string($room) && trim($room) !== '') {
                $map[(int) $eid] = $roomTitles[$room] ?? $room;
            }
        }

        return $map;
    }

    /**
     * Open encounter ids the patient tracker has mapped to each event for
     * one day (0 means none was created).
     *
     * @return array<int, int>
     */
    public function encountersByEventId(string $date): array
    {
        $records = QueryUtils::fetchRecords(
            'SELECT eid, encounter FROM patient_tracker WHERE apptdate = ?',
            [$date],
        );

        $map = [];
        foreach ($records as $record) {
            $eid = $record['eid'] ?? null;
            $encounter = $record['encounter'] ?? null;
            if (is_numeric($eid) && is_numeric($encounter) && (int) $encounter > 0) {
                $map[(int) $eid] = (int) $encounter;
            }
        }

        return $map;
    }

    /**
     * The active room options for the room picker, in list order.
     *
     * @return list<array{code: string, label: string}>
     */
    public function roomOptions(): array
    {
        $records = QueryUtils::fetchRecords(
            "SELECT option_id, title FROM list_options "
            . "WHERE list_id = 'patient_flow_board_rooms' AND activity = 1 ORDER BY seq, title",
        );

        $options = [];
        foreach ($records as $record) {
            $code = $record['option_id'] ?? null;
            $title = $record['title'] ?? null;
            if (is_string($code) && $code !== '' && is_string($title) && $title !== '') {
                $options[] = ['code' => $code, 'label' => $title];
            }
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function roomTitlesByCode(): array
    {
        $map = [];
        foreach ($this->roomOptions() as $option) {
            $map[$option['code']] = $option['label'];
        }

        return $map;
    }
}
