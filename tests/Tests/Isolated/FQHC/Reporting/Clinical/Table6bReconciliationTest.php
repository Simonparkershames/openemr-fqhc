<?php

/**
 * Isolated tests for the Table 6B denominator-vs-patient-universe
 * reconciliation.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\FQHC\Reporting\Clinical;

use OpenEMR\FQHC\Reporting\Clinical\Table6bReconciliation;
use OpenEMR\FQHC\Reporting\Clinical\Table6bReport;
use OpenEMR\FQHC\Reporting\Clinical\UdsClinicalMeasure;
use OpenEMR\FQHC\Reporting\Clinical\UdsMeasurePopulationCounts;
use PHPUnit\Framework\TestCase;

final class Table6bReconciliationTest extends TestCase
{
    public function testFlagsOnlyMeasuresWhoseDenominatorExceedsTheUniverse(): void
    {
        $report = new Table6bReport(2026, [
            UdsClinicalMeasure::ControllingHighBloodPressure->cmsId() =>
                new UdsMeasurePopulationCounts(300, 250, 0, 0, 100),
            UdsClinicalMeasure::ColorectalCancerScreening->cmsId() =>
                new UdsMeasurePopulationCounts(150, 150, 0, 0, 100),
        ]);

        $exceeding = (new Table6bReconciliation())->measuresExceedingPatientUniverse($report, 200);

        self::assertSame([UdsClinicalMeasure::ControllingHighBloodPressure], $exceeding);
    }

    public function testDenominatorEqualToTheUniverseIsConsistent(): void
    {
        $report = new Table6bReport(2026, [
            UdsClinicalMeasure::ControllingHighBloodPressure->cmsId() =>
                new UdsMeasurePopulationCounts(200, 200, 0, 0, 100),
        ]);

        self::assertSame([], (new Table6bReconciliation())->measuresExceedingPatientUniverse($report, 200));
    }

    public function testPendingMeasuresAreNeverFlagged(): void
    {
        $report = new Table6bReport(2026, []);

        self::assertSame([], (new Table6bReconciliation())->measuresExceedingPatientUniverse($report, 0));
    }
}
