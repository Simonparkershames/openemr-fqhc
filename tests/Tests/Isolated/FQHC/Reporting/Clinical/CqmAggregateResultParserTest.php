<?php

/**
 * Isolated tests for parsing the CQM engine's aggregate hash into UDS
 * population counts.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\FQHC\Reporting\Clinical;

use OpenEMR\FQHC\Reporting\Clinical\CqmAggregateResultParser;
use OpenEMR\FQHC\Reporting\Clinical\UdsClinicalMeasure;
use PHPUnit\Framework\TestCase;

final class CqmAggregateResultParserTest extends TestCase
{
    private CqmAggregateResultParser $parser;

    protected function setUp(): void
    {
        $this->parser = new CqmAggregateResultParser();
    }

    public function testReadsTheSelectedPopulationSet(): void
    {
        $counts = $this->parser->countsFor(UdsClinicalMeasure::ControllingHighBloodPressure, [
            'PopulationSet_1' => [
                'IPP' => 120,
                'DENOM' => 100,
                'DENEX' => 5,
                'DENEXCEP' => 3,
                'NUMER' => 60,
                'supplemental_data' => [],
                'observations' => [],
                'measure_id' => 'irrelevant',
            ],
        ]);

        self::assertNotNull($counts);
        self::assertSame(120, $counts->initialPopulation);
        self::assertSame(100, $counts->denominator);
        self::assertSame(5, $counts->denominatorExclusions);
        self::assertSame(3, $counts->denominatorExceptions);
        self::assertSame(60, $counts->numerator);
        self::assertSame(92, $counts->eligibleDenominator());
    }

    public function testTobaccoMeasureReadsItsCombinedThirdRate(): void
    {
        $aggregate = [
            'PopulationSet_1' => ['IPP' => 100, 'DENOM' => 100, 'NUMER' => 90],
            'PopulationSet_2' => ['IPP' => 40, 'DENOM' => 40, 'NUMER' => 10],
            'PopulationSet_3' => ['IPP' => 100, 'DENOM' => 100, 'NUMER' => 70],
        ];

        $counts = $this->parser->countsFor(UdsClinicalMeasure::TobaccoUseScreeningCessation, $aggregate);

        self::assertNotNull($counts);
        self::assertSame(70, $counts->numerator);
    }

    public function testPopulationsTheMeasureDoesNotDefineCountAsZero(): void
    {
        $counts = $this->parser->countsFor(UdsClinicalMeasure::ColorectalCancerScreening, [
            'PopulationSet_1' => ['IPP' => 50, 'DENOM' => 50, 'NUMER' => 20],
        ]);

        self::assertNotNull($counts);
        self::assertSame(0, $counts->denominatorExclusions);
        self::assertSame(0, $counts->denominatorExceptions);
    }

    public function testUnresolvedPopulationSetSelectionStaysPending(): void
    {
        $counts = $this->parser->countsFor(UdsClinicalMeasure::WeightAssessmentChildrenAdolescents, [
            'PopulationSet_1' => ['IPP' => 50, 'DENOM' => 50, 'NUMER' => 20],
        ]);

        self::assertNull($counts);
    }

    public function testMissingSelectedPopulationSetStaysPending(): void
    {
        $counts = $this->parser->countsFor(UdsClinicalMeasure::TobaccoUseScreeningCessation, [
            'PopulationSet_1' => ['IPP' => 50, 'DENOM' => 50, 'NUMER' => 20],
        ]);

        self::assertNull($counts);
    }

    public function testMalformedCountStaysPendingRatherThanGuess(): void
    {
        $counts = $this->parser->countsFor(UdsClinicalMeasure::ControllingHighBloodPressure, [
            'PopulationSet_1' => ['IPP' => 50, 'DENOM' => 'fifty', 'NUMER' => 20],
        ]);

        self::assertNull($counts);
    }

    public function testWholeFloatCountsAreAccepted(): void
    {
        $counts = $this->parser->countsFor(UdsClinicalMeasure::ControllingHighBloodPressure, [
            'PopulationSet_1' => ['IPP' => 50.0, 'DENOM' => 40.0, 'NUMER' => 20.0],
        ]);

        self::assertNotNull($counts);
        self::assertSame(40, $counts->denominator);
        self::assertSame(20, $counts->numerator);
    }

    public function testFractionalCountStaysPending(): void
    {
        $counts = $this->parser->countsFor(UdsClinicalMeasure::ControllingHighBloodPressure, [
            'PopulationSet_1' => ['IPP' => 50, 'DENOM' => 40.5, 'NUMER' => 20],
        ]);

        self::assertNull($counts);
    }
}
