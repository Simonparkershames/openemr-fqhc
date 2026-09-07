<?php

/**
 * Isolated tests for the manager-workspace year-over-year utilization
 * comparison (issue #39): totals, deltas, per-category rows, and the
 * activity flags that drive the home's empty state.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\FQHC\Reporting;

use OpenEMR\FQHC\Reporting\Table5ReportBuilder;
use OpenEMR\FQHC\Reporting\Table5VisitRecord;
use OpenEMR\FQHC\Reporting\UdsServiceCategory;
use OpenEMR\FQHC\Reporting\UtilizationCategoryComparison;
use OpenEMR\FQHC\Reporting\UtilizationComparison;
use PHPUnit\Framework\TestCase;

final class UtilizationComparisonTest extends TestCase
{
    public function testComparesGrandTotalVisitsAcrossYears(): void
    {
        $comparison = new UtilizationComparison(
            2025,
            2024,
            (new Table5ReportBuilder())->build([
                new Table5VisitRecord(1, UdsServiceCategory::Medical, false),
                new Table5VisitRecord(1, UdsServiceCategory::Medical, true),
                new Table5VisitRecord(2, UdsServiceCategory::Dental, false),
            ]),
            (new Table5ReportBuilder())->build([
                new Table5VisitRecord(3, UdsServiceCategory::Medical, false),
            ]),
        );

        self::assertSame(2025, $comparison->year);
        self::assertSame(2024, $comparison->priorYear);
        self::assertSame(3, $comparison->currentVisits());
        self::assertSame(1, $comparison->priorVisits());
        self::assertSame(2, $comparison->visitsDelta());
        self::assertTrue($comparison->hasActivity());
    }

    public function testVisitsDeltaGoesNegativeWhenVolumeFalls(): void
    {
        $comparison = new UtilizationComparison(
            2025,
            2024,
            (new Table5ReportBuilder())->build([
                new Table5VisitRecord(1, UdsServiceCategory::Medical, false),
            ]),
            (new Table5ReportBuilder())->build([
                new Table5VisitRecord(1, UdsServiceCategory::Medical, false),
                new Table5VisitRecord(2, UdsServiceCategory::Medical, false),
                new Table5VisitRecord(3, UdsServiceCategory::Dental, false),
            ]),
        );

        self::assertSame(-2, $comparison->visitsDelta());
    }

    public function testCategoriesCoverEveryServiceLineInTableOrder(): void
    {
        $comparison = new UtilizationComparison(
            2025,
            2024,
            (new Table5ReportBuilder())->build([]),
            (new Table5ReportBuilder())->build([]),
        );

        $categories = array_map(
            static fn(UtilizationCategoryComparison $row): UdsServiceCategory => $row->category,
            $comparison->categories(),
        );

        self::assertSame(UdsServiceCategory::cases(), $categories);
        self::assertFalse($comparison->hasActivity());
    }

    public function testPerCategoryDeltaAndActivityReflectBothYears(): void
    {
        $comparison = new UtilizationComparison(
            2025,
            2024,
            (new Table5ReportBuilder())->build([
                new Table5VisitRecord(1, UdsServiceCategory::Medical, false),
                new Table5VisitRecord(1, UdsServiceCategory::Medical, true),
            ]),
            (new Table5ReportBuilder())->build([
                new Table5VisitRecord(2, UdsServiceCategory::Dental, false),
            ]),
        );

        $byCategory = [];
        foreach ($comparison->categories() as $row) {
            $byCategory[$row->category->value] = $row;
        }

        $medical = $byCategory[UdsServiceCategory::Medical->value];
        self::assertSame(2, $medical->currentVisits);
        self::assertSame(0, $medical->priorVisits);
        self::assertSame(2, $medical->delta());
        self::assertTrue($medical->hasActivity());

        $dental = $byCategory[UdsServiceCategory::Dental->value];
        self::assertSame(-1, $dental->delta());
        self::assertTrue($dental->hasActivity());

        $vision = $byCategory[UdsServiceCategory::Vision->value];
        self::assertSame(0, $vision->delta());
        self::assertFalse($vision->hasActivity());
    }
}
