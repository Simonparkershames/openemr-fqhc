<?php

/**
 * Isolated tests for the eCQM measure catalog, driven by a temporary
 * directory shaped like the oe-cqm-parsers package layout.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\FQHC\Reporting\Clinical;

use OpenEMR\FQHC\Reporting\Clinical\EcqmMeasureCatalog;
use OpenEMR\FQHC\Reporting\Clinical\UdsClinicalMeasure;
use PHPUnit\Framework\TestCase;

final class EcqmMeasureCatalogTest extends TestCase
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

    public function testResolvesInstalledMeasureDirectoriesForTheYear(): void
    {
        $cmsId = UdsClinicalMeasure::ControllingHighBloodPressure->cmsId();
        $measureDirectory = $this->installMeasure(2026, $cmsId);

        $paths = (new EcqmMeasureCatalog($this->basePath))->installedMeasurePaths(2026);

        self::assertSame([$cmsId => $measureDirectory], $paths);
    }

    public function testSkipsMeasuresMissingTheirMeasureOrValueSetFile(): void
    {
        $withoutValueSets = UdsClinicalMeasure::ColorectalCancerScreening->cmsId();
        $this->installMeasure(2026, $withoutValueSets, includeValueSets: false);

        $withoutMeasureJson = UdsClinicalMeasure::BreastCancerScreening->cmsId();
        $this->installMeasure(2026, $withoutMeasureJson, includeMeasureJson: false);

        self::assertSame([], (new EcqmMeasureCatalog($this->basePath))->installedMeasurePaths(2026));
    }

    public function testNeverOffersMeasuresWithoutAResolvedPopulationSetSelection(): void
    {
        $unresolved = UdsClinicalMeasure::WeightAssessmentChildrenAdolescents;
        self::assertNull($unresolved->reportedPopulationSetId());
        $this->installMeasure(2026, $unresolved->cmsId());

        self::assertSame([], (new EcqmMeasureCatalog($this->basePath))->installedMeasurePaths(2026));
    }

    public function testReportsNothingWhenTheYearHasNoMeasureDirectory(): void
    {
        self::assertSame([], (new EcqmMeasureCatalog($this->basePath))->installedMeasurePaths(2026));
    }

    public function testYearsResolveIndependently(): void
    {
        $cmsId = UdsClinicalMeasure::ControllingHighBloodPressure->cmsId();
        $this->installMeasure(2026, $cmsId);

        self::assertSame([], (new EcqmMeasureCatalog($this->basePath))->installedMeasurePaths(2025));
    }

    private function installMeasure(
        int $year,
        string $cmsId,
        bool $includeMeasureJson = true,
        bool $includeValueSets = true,
    ): string {
        $measureDirectory = $this->basePath . '/' . $year . '_reporting_period/json_measures/' . $cmsId;
        mkdir($measureDirectory, 0o777, true);
        if ($includeMeasureJson) {
            file_put_contents($measureDirectory . '/' . $cmsId . '.json', '{}');
        }
        if ($includeValueSets) {
            file_put_contents($measureDirectory . '/value_sets.json', '[]');
        }

        return $measureDirectory;
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
