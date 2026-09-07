<?php

/**
 * Tests the provider care-gap panel builder (issue #38): attaching each due
 * screening to its patient, urgency ordering (past due → due → due soon), and
 * stable patient order within a status. Runs in isolation (no DB/Docker).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\FQHC\Provider;

use OpenEMR\FQHC\Provider\CareGap;
use OpenEMR\FQHC\Provider\CareGapPanelBuilder;
use OpenEMR\FQHC\Provider\PanelPatient;
use OpenEMR\FQHC\Rooming\ScreeningDue;
use OpenEMR\FQHC\Rooming\ScreeningDueStatus;
use PHPUnit\Framework\TestCase;

final class CareGapPanelBuilderTest extends TestCase
{
    private CareGapPanelBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new CareGapPanelBuilder();
    }

    public function testFlattensScreeningsOntoTheirPatients(): void
    {
        $patients = [new PanelPatient(101, 'Doe, Jane')];
        $screenings = [
            101 => [
                new ScreeningDue('A1c due', ScreeningDueStatus::Due),
                new ScreeningDue('Flu shot', ScreeningDueStatus::Due),
            ],
        ];

        $gaps = $this->builder->build($patients, $screenings);

        self::assertCount(2, $gaps);
        self::assertContainsOnlyInstancesOf(CareGap::class, $gaps);
        self::assertSame('Doe, Jane', $gaps[0]->patientName);
        self::assertSame(101, $gaps[0]->pid);
    }

    public function testOrdersByUrgencyAcrossPatients(): void
    {
        $patients = [
            new PanelPatient(101, 'Alpha'),
            new PanelPatient(102, 'Bravo'),
        ];
        $screenings = [
            101 => [new ScreeningDue('Due soon item', ScreeningDueStatus::SoonDue)],
            102 => [
                new ScreeningDue('Past due item', ScreeningDueStatus::PastDue),
                new ScreeningDue('Due item', ScreeningDueStatus::Due),
            ],
        ];

        $gaps = $this->builder->build($patients, $screenings);

        self::assertSame(
            ['Past due item', 'Due item', 'Due soon item'],
            array_map(static fn(CareGap $gap): string => $gap->screening->label, $gaps),
        );
    }

    public function testKeepsPatientOrderStableWithinSameStatus(): void
    {
        $patients = [
            new PanelPatient(101, 'Alpha'),
            new PanelPatient(102, 'Bravo'),
            new PanelPatient(103, 'Charlie'),
        ];
        $screenings = [
            103 => [new ScreeningDue('Charlie gap', ScreeningDueStatus::Due)],
            101 => [new ScreeningDue('Alpha gap', ScreeningDueStatus::Due)],
            102 => [new ScreeningDue('Bravo gap', ScreeningDueStatus::Due)],
        ];

        $gaps = $this->builder->build($patients, $screenings);

        self::assertSame(
            ['Alpha', 'Bravo', 'Charlie'],
            array_map(static fn(CareGap $gap): string => $gap->patientName, $gaps),
        );
    }

    public function testPatientsWithoutScreeningsContributeNothing(): void
    {
        $patients = [
            new PanelPatient(101, 'Alpha'),
            new PanelPatient(102, 'Bravo'),
        ];
        $screenings = [101 => [new ScreeningDue('Alpha gap', ScreeningDueStatus::Due)]];

        $gaps = $this->builder->build($patients, $screenings);

        self::assertCount(1, $gaps);
        self::assertSame(101, $gaps[0]->pid);
    }

    public function testEmptyPanelYieldsNoGaps(): void
    {
        self::assertSame([], $this->builder->build([], []));
    }
}
