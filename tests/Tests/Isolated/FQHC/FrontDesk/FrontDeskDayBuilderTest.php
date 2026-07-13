<?php

/**
 * Tests the front-desk day builder (issue #36): time ordering, phase counts,
 * arrival-readiness flags, display formatting, and the needs-attention rule.
 * Runs in isolation (no DB/Docker).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\FQHC\FrontDesk;

use OpenEMR\FQHC\FrontDesk\AppointmentPhase;
use OpenEMR\FQHC\FrontDesk\FrontDeskDayBuilder;
use OpenEMR\FQHC\FrontDesk\ReadinessFlag;
use OpenEMR\FQHC\FrontDesk\ScheduleRow;
use PHPUnit\Framework\TestCase;

final class FrontDeskDayBuilderTest extends TestCase
{
    private FrontDeskDayBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new FrontDeskDayBuilder();
    }

    /**
     * @param array{
     *     eventId?: int,
     *     pid?: int,
     *     firstName?: string,
     *     lastName?: string,
     *     sexCode?: ?string,
     *     dateOfBirth?: ?string,
     *     startTime?: string,
     *     durationSeconds?: int,
     *     providerName?: string,
     *     categoryName?: string,
     *     statusCode?: string,
     *     statusTitle?: string,
     *     hasInsuranceOnFile?: bool,
     *     hasIncomeDetermination?: bool
     * } $overrides
     */
    private static function row(array $overrides = []): ScheduleRow
    {
        $defaults = [
            'eventId' => 100,
            'pid' => 7,
            'firstName' => 'Maria',
            'lastName' => 'Alvarez',
            'sexCode' => 'Female',
            'dateOfBirth' => '1988-04-02',
            'startTime' => '09:00:00',
            'durationSeconds' => 1800,
            'providerName' => 'Dana Nguyen',
            'categoryName' => 'Office Visit',
            'statusCode' => '-',
            'statusTitle' => '- None',
            'hasInsuranceOnFile' => true,
            'hasIncomeDetermination' => true,
        ];
        $values = array_merge($defaults, $overrides);

        return new ScheduleRow(
            $values['eventId'],
            $values['pid'],
            $values['firstName'],
            $values['lastName'],
            $values['sexCode'],
            $values['dateOfBirth'],
            $values['startTime'],
            $values['durationSeconds'],
            $values['providerName'],
            $values['categoryName'],
            $values['statusCode'],
            $values['statusTitle'],
            $values['hasInsuranceOnFile'],
            $values['hasIncomeDetermination'],
        );
    }

    public function testOrdersAppointmentsByStartTimeThenEventId(): void
    {
        $day = $this->builder->build('2026-07-12', [
            self::row(['eventId' => 3, 'startTime' => '13:30:00']),
            self::row(['eventId' => 2, 'startTime' => '08:15:00']),
            self::row(['eventId' => 1, 'startTime' => '13:30:00']),
        ]);

        self::assertSame(
            [2, 1, 3],
            array_map(static fn($appointment): int => $appointment->eventId, $day->appointments),
        );
    }

    public function testFormatsDisplayFields(): void
    {
        $day = $this->builder->build('2026-07-12', [
            self::row(['startTime' => '13:30:00', 'durationSeconds' => 900]),
        ]);
        $appointment = $day->appointments[0];

        self::assertSame('Alvarez, Maria', $appointment->patientName);
        self::assertSame('1:30 PM', $appointment->timeDisplay);
        self::assertSame(15, $appointment->durationMinutes);
        self::assertSame('Dana Nguyen', $appointment->providerName);
        self::assertSame('Office Visit', $appointment->categoryName);
        self::assertSame('- None', $appointment->statusTitle);
    }

    public function testFallsBackToPidWhenNameIsBlank(): void
    {
        $day = $this->builder->build('2026-07-12', [
            self::row(['firstName' => '', 'lastName' => '', 'pid' => 42]),
        ]);

        self::assertSame('Patient #42', $day->appointments[0]->patientName);
    }

    public function testCompletePatientHasNoReadinessFlags(): void
    {
        $day = $this->builder->build('2026-07-12', [self::row()]);

        self::assertTrue($day->appointments[0]->isReady());
        self::assertSame(0, $day->needsAttentionCount());
    }

    public function testFlagsEveryArrivalReadinessGap(): void
    {
        $day = $this->builder->build('2026-07-12', [
            self::row([
                'sexCode' => null,
                'dateOfBirth' => '0000-00-00',
                'hasInsuranceOnFile' => false,
                'hasIncomeDetermination' => false,
            ]),
        ]);

        self::assertSame(
            [
                ReadinessFlag::MissingDateOfBirth,
                ReadinessFlag::MissingSex,
                ReadinessFlag::NoInsuranceOnFile,
                ReadinessFlag::NoIncomeDetermination,
            ],
            $day->appointments[0]->readinessFlags,
        );
    }

    public function testCountsPhasesAcrossTheDay(): void
    {
        $day = $this->builder->build('2026-07-12', [
            self::row(['eventId' => 1, 'statusCode' => '-']),
            self::row(['eventId' => 2, 'statusCode' => '@']),
            self::row(['eventId' => 3, 'statusCode' => '@']),
            self::row(['eventId' => 4, 'statusCode' => '<']),
            self::row(['eventId' => 5, 'statusCode' => '>']),
            self::row(['eventId' => 6, 'statusCode' => 'x']),
        ]);

        self::assertSame(6, $day->total());
        self::assertSame(1, $day->countInPhase(AppointmentPhase::Expected));
        self::assertSame(2, $day->countInPhase(AppointmentPhase::Arrived));
        self::assertSame(1, $day->countInPhase(AppointmentPhase::WithCareTeam));
        self::assertSame(1, $day->countInPhase(AppointmentPhase::CheckedOut));
        self::assertSame(1, $day->countInPhase(AppointmentPhase::NotComing));
    }

    public function testNeedsAttentionIgnoresPatientsWhoAreDoneOrNotComing(): void
    {
        $day = $this->builder->build('2026-07-12', [
            self::row(['eventId' => 1, 'statusCode' => '-', 'hasInsuranceOnFile' => false]),
            self::row(['eventId' => 2, 'statusCode' => '@', 'hasIncomeDetermination' => false]),
            self::row(['eventId' => 3, 'statusCode' => '>', 'hasInsuranceOnFile' => false]),
            self::row(['eventId' => 4, 'statusCode' => 'x', 'hasInsuranceOnFile' => false]),
        ]);

        self::assertSame(2, $day->needsAttentionCount());
    }

    public function testKeepsRawTimeWhenItDoesNotParse(): void
    {
        $day = $this->builder->build('2026-07-12', [
            self::row(['startTime' => '']),
        ]);

        self::assertSame('', $day->appointments[0]->timeDisplay);
    }

    public function testRejectsNonPositiveIds(): void
    {
        $this->expectException(\DomainException::class);

        self::row(['eventId' => 0]);
    }
}
