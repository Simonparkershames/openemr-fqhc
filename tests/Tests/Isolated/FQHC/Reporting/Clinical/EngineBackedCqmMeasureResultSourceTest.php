<?php

/**
 * Isolated tests for the engine-backed measure result source, driven by an
 * in-memory calculation engine and a temporary measure catalog.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\FQHC\Reporting\Clinical;

use OpenEMR\FQHC\Reporting\Clinical\CqmCalculationEngine;
use OpenEMR\FQHC\Reporting\Clinical\EcqmMeasureCatalog;
use OpenEMR\FQHC\Reporting\Clinical\EngineBackedCqmMeasureResultSource;
use OpenEMR\FQHC\Reporting\Clinical\UdsClinicalMeasure;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;

final class EngineBackedCqmMeasureResultSourceTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $basePath = tempnam(sys_get_temp_dir(), 'fqhc-measures-');
        self::assertIsString($basePath);
        unlink($basePath);
        mkdir($basePath);
        $this->basePath = $basePath;
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->basePath);
    }

    public function testParsesEngineAggregatesForInstalledMeasures(): void
    {
        $bloodPressure = UdsClinicalMeasure::ControllingHighBloodPressure->cmsId();
        $this->installMeasure(2026, $bloodPressure);

        $engine = $this->engineReturning([
            $bloodPressure => [
                'PopulationSet_1' => ['IPP' => 120, 'DENOM' => 100, 'DENEX' => 5, 'DENEXCEP' => 3, 'NUMER' => 60],
            ],
        ]);

        $results = $this->source($engine)->resultsForYear(2026);

        self::assertArrayHasKey($bloodPressure, $results);
        self::assertSame(100, $results[$bloodPressure]->denominator);
        self::assertSame(60, $results[$bloodPressure]->numerator);
        self::assertCount(1, $results);
        self::assertSame([[$bloodPressure], 2026], $engine->lastRequest);
    }

    public function testNothingInstalledNeverTouchesTheEngine(): void
    {
        $engine = $this->engineReturning([]);

        self::assertSame([], $this->source($engine)->resultsForYear(2026));
        self::assertNull($engine->lastRequest);
    }

    public function testEngineFailureLogsAndDegradesToPending(): void
    {
        $this->installMeasure(2026, UdsClinicalMeasure::ControllingHighBloodPressure->cmsId());

        $engine = new class implements CqmCalculationEngine {
            public function aggregatePopulationCounts(array $measurePathsByCmsId, int $year): array
            {
                throw new \RuntimeException('node service down');
            }
        };
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $messages = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->messages[] = $level . ': ' . $message;
            }
        };

        $source = new EngineBackedCqmMeasureResultSource(
            new EcqmMeasureCatalog($this->basePath),
            $engine,
            $logger,
        );

        self::assertSame([], $source->resultsForYear(2026));
        self::assertCount(1, $logger->messages);
        self::assertStringStartsWith('error:', $logger->messages[0]);
    }

    public function testMalformedAggregateForOneMeasureLeavesOthersLive(): void
    {
        $bloodPressure = UdsClinicalMeasure::ControllingHighBloodPressure->cmsId();
        $colorectal = UdsClinicalMeasure::ColorectalCancerScreening->cmsId();
        $this->installMeasure(2026, $bloodPressure);
        $this->installMeasure(2026, $colorectal);

        $engine = $this->engineReturning([
            $bloodPressure => ['PopulationSet_1' => ['IPP' => 10, 'DENOM' => 10, 'NUMER' => 5]],
            $colorectal => ['PopulationSet_1' => ['IPP' => 10, 'DENOM' => 'broken', 'NUMER' => 5]],
        ]);

        $results = $this->source($engine)->resultsForYear(2026);

        self::assertArrayHasKey($bloodPressure, $results);
        self::assertArrayNotHasKey($colorectal, $results);
    }

    /**
     * @param array<string, array<string, mixed>> $aggregatesByCmsId
     * @return CqmCalculationEngine&object{lastRequest: array{list<string>, int}|null}
     */
    private function engineReturning(array $aggregatesByCmsId): object
    {
        return new class ($aggregatesByCmsId) implements CqmCalculationEngine {
            /** @var array{list<string>, int}|null */
            public ?array $lastRequest = null;

            /**
             * @param array<string, array<string, mixed>> $aggregatesByCmsId
             */
            public function __construct(private readonly array $aggregatesByCmsId)
            {
            }

            public function aggregatePopulationCounts(array $measurePathsByCmsId, int $year): array
            {
                $this->lastRequest = [array_keys($measurePathsByCmsId), $year];

                return $this->aggregatesByCmsId;
            }
        };
    }

    private function source(CqmCalculationEngine $engine): EngineBackedCqmMeasureResultSource
    {
        return new EngineBackedCqmMeasureResultSource(
            new EcqmMeasureCatalog($this->basePath),
            $engine,
            new NullLogger(),
        );
    }

    private function installMeasure(int $year, string $cmsId): void
    {
        $measureDirectory = $this->basePath . '/' . $year . '_reporting_period/json_measures/' . $cmsId;
        mkdir($measureDirectory, 0o777, true);
        file_put_contents($measureDirectory . '/' . $cmsId . '.json', '{}');
        file_put_contents($measureDirectory . '/value_sets.json', '[]');
    }

    private function removeDirectory(string $directory): void
    {
        $entries = scandir($directory);
        if ($entries === false) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($directory);
    }
}
