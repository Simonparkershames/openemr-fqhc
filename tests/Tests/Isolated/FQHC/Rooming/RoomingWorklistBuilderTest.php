<?php

/**
 * Tests the MA rooming worklist builder (issue #37): phase partitioning,
 * time-order preservation, and room/encounter/glance attachment. Runs in
 * isolation (no DB/Docker).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\FQHC\Rooming;

use OpenEMR\FQHC\FrontDesk\FrontDeskDayBuilder;
use OpenEMR\FQHC\FrontDesk\ScheduleRow;
use OpenEMR\FQHC\Rooming\PatientGlance;
use OpenEMR\FQHC\Rooming\RoomingQueueEntry;
use OpenEMR\FQHC\Rooming\RoomingWorklist;
use OpenEMR\FQHC\Rooming\RoomingWorklistBuilder;
use OpenEMR\FQHC\Rooming\ScreeningDue;
use OpenEMR\FQHC\Rooming\ScreeningDueStatus;
use PHPUnit\Framework\TestCase;

final class RoomingWorklistBuilderTest extends TestCase
{
    private RoomingWorklistBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new RoomingWorklistBuilder();
    }

    private static function row(int $eventId, int $pid, string $statusCode, string $startTime): ScheduleRow
    {
        return new ScheduleRow(
            $eventId,
            $pid,
            'First' . $pid,
            'Last' . $pid,
            'Female',
            '1990-01-01',
            $startTime,
            900,
            'Dana Nguyen',
            'Office Visit',
            $statusCode,
            $statusCode,
            true,
            true,
        );
    }

    /**
     * @param list<ScheduleRow> $rows
     * @param array<int, string> $rooms
     * @param array<int, int> $encounters
     * @param array<int, PatientGlance> $glances
     */
    private function build(array $rows, array $rooms = [], array $encounters = [], array $glances = []): RoomingWorklist
    {
        $day = (new FrontDeskDayBuilder())->build('2026-07-12', $rows);

        return $this->builder->build($day, $rooms, $encounters, $glances);
    }

    public function testPartitionsArrivedAndRoomedPatientsOnly(): void
    {
        $worklist = $this->build([
            self::row(1, 11, '-', '08:00:00'),  // expected — not on worklist
            self::row(2, 12, '@', '08:30:00'),  // arrived — awaiting rooming
            self::row(3, 13, '~', '09:00:00'),  // arrived late — awaiting rooming
            self::row(4, 14, '<', '09:30:00'),  // roomed — with care team
            self::row(5, 15, '>', '10:00:00'),  // checked out — done
            self::row(6, 16, 'x', '10:30:00'),  // cancelled — not coming
        ]);

        self::assertSame(
            [2, 3],
            array_map(static fn(RoomingQueueEntry $entry): int => $entry->appointment->eventId, $worklist->awaitingRooming),
        );
        self::assertSame(
            [4],
            array_map(static fn(RoomingQueueEntry $entry): int => $entry->appointment->eventId, $worklist->withCareTeam),
        );
        self::assertSame(3, $worklist->total());
    }

    public function testQueuesKeepTheDaysTimeOrder(): void
    {
        $worklist = $this->build([
            self::row(1, 11, '@', '10:00:00'),
            self::row(2, 12, '@', '08:00:00'),
            self::row(3, 13, '@', '09:00:00'),
        ]);

        self::assertSame(
            [2, 3, 1],
            array_map(static fn(RoomingQueueEntry $entry): int => $entry->appointment->eventId, $worklist->awaitingRooming),
        );
    }

    public function testAttachesRoomEncounterAndGlance(): void
    {
        $glance = new PatientGlance(
            ['Penicillin'],
            ['Metformin', 'Lisinopril'],
            [new ScreeningDue('Colon cancer screening', ScreeningDueStatus::Due)],
        );

        $worklist = $this->build(
            [self::row(4, 14, '<', '09:30:00')],
            [4 => 'Exam 2'],
            [4 => 321],
            [14 => $glance],
        );

        $entry = $worklist->withCareTeam[0];
        self::assertSame('Exam 2', $entry->roomLabel);
        self::assertSame(321, $entry->encounterId);
        self::assertSame($glance, $entry->glance);
    }

    public function testMissingContextDefaultsToNullsAndEmptyGlance(): void
    {
        $worklist = $this->build([self::row(2, 12, '@', '08:30:00')]);

        $entry = $worklist->awaitingRooming[0];
        self::assertNull($entry->roomLabel);
        self::assertNull($entry->encounterId);
        self::assertSame([], $entry->glance->allergies);
        self::assertSame([], $entry->glance->medications);
        self::assertSame([], $entry->glance->screeningsDue);
    }

    public function testAllListsWaitingQueueFirst(): void
    {
        $worklist = $this->build([
            self::row(4, 14, '<', '08:00:00'),
            self::row(2, 12, '@', '09:00:00'),
        ]);

        self::assertSame(
            [2, 4],
            array_map(static fn(RoomingQueueEntry $entry): int => $entry->appointment->eventId, $worklist->all()),
        );
    }
}
