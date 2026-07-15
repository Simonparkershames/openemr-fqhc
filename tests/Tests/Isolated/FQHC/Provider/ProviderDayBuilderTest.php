<?php

/**
 * Tests the provider day builder (issue #38): filtering the shared front-desk
 * day to one provider, preserving time order, attaching rooming context, and
 * the ProviderDay status counts. Runs in isolation (no DB/Docker).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\FQHC\Provider;

use OpenEMR\FQHC\FrontDesk\FrontDeskDay;
use OpenEMR\FQHC\FrontDesk\FrontDeskDayBuilder;
use OpenEMR\FQHC\FrontDesk\ScheduleRow;
use OpenEMR\FQHC\Provider\ProviderDayBuilder;
use PHPUnit\Framework\TestCase;

final class ProviderDayBuilderTest extends TestCase
{
    private ProviderDayBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ProviderDayBuilder();
    }

    private static function row(
        int $eventId,
        int $pid,
        int $providerId,
        string $statusCode,
        string $startTime,
    ): ScheduleRow {
        return new ScheduleRow(
            $eventId,
            $pid,
            'First' . $pid,
            'Last' . $pid,
            'Female',
            '1990-01-01',
            $startTime,
            900,
            $providerId,
            'Dr. Provider ' . $providerId,
            'Office Visit',
            $statusCode,
            $statusCode,
            true,
            true,
        );
    }

    /**
     * @param list<ScheduleRow> $rows
     */
    private function day(array $rows): FrontDeskDay
    {
        return (new FrontDeskDayBuilder())->build('2026-07-15', $rows);
    }

    public function testKeepsOnlyTheGivenProvidersAppointments(): void
    {
        $day = $this->day([
            self::row(1, 101, 7, '@', '09:00:00'),
            self::row(2, 102, 9, '@', '09:30:00'),
            self::row(3, 103, 7, '<', '10:00:00'),
        ]);

        $providerDay = $this->builder->build($day, 7, [], []);

        self::assertSame(
            [101, 103],
            array_map(static fn($entry): int => $entry->appointment->pid, $providerDay->entries),
        );
    }

    public function testPreservesTheDaysTimeOrder(): void
    {
        $day = $this->day([
            self::row(3, 103, 7, '@', '13:30:00'),
            self::row(1, 101, 7, '@', '08:15:00'),
            self::row(2, 102, 7, '@', '10:00:00'),
        ]);

        $providerDay = $this->builder->build($day, 7, [], []);

        self::assertSame(
            [101, 102, 103],
            array_map(static fn($entry): int => $entry->appointment->pid, $providerDay->entries),
        );
    }

    public function testAttachesRoomLabelAndEncounterByEventId(): void
    {
        $day = $this->day([
            self::row(1, 101, 7, '<', '09:00:00'),
            self::row(2, 102, 7, '@', '09:30:00'),
        ]);

        $providerDay = $this->builder->build($day, 7, [1 => 'Room 2'], [1 => 555]);

        $roomed = $providerDay->entries[0];
        self::assertSame('Room 2', $roomed->roomLabel);
        self::assertSame(555, $roomed->encounterId);
        self::assertTrue($roomed->isReadyForProvider());

        $arrived = $providerDay->entries[1];
        self::assertNull($arrived->roomLabel);
        self::assertNull($arrived->encounterId);
        self::assertFalse($arrived->isReadyForProvider());
    }

    public function testCountsVisitsByPhase(): void
    {
        $day = $this->day([
            self::row(1, 101, 7, '@', '09:00:00'),
            self::row(2, 102, 7, '<', '09:30:00'),
            self::row(3, 103, 7, '<', '10:00:00'),
            self::row(4, 104, 7, '>', '10:30:00'),
            self::row(5, 105, 7, '-', '11:00:00'),
        ]);

        $providerDay = $this->builder->build($day, 7, [], []);

        self::assertSame(5, $providerDay->total());
        self::assertSame(1, $providerDay->arrivedCount());
        self::assertSame(2, $providerDay->readyCount());
        self::assertSame(1, $providerDay->checkedOutCount());
    }

    public function testUnknownProviderYieldsEmptyDay(): void
    {
        $day = $this->day([
            self::row(1, 101, 7, '@', '09:00:00'),
        ]);

        $providerDay = $this->builder->build($day, 42, [], []);

        self::assertSame(0, $providerDay->total());
        self::assertSame([], $providerDay->entries);
    }
}
