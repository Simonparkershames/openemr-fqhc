<?php

/**
 * Resolves which UDS-mapped eCQM measure definitions are installed for a
 * reporting year.
 *
 * The CQM engine's measure files ship in the oe-cqm-parsers package as one
 * directory per CMS eCQM id under
 * {basePath}/{year}_reporting_period/json_measures (the same layout
 * MeasureService reads, minus its dependency on the cqm_performance_period
 * global — the UDS report chooses its own year). A mapped measure whose
 * directory, measure JSON, or value-set JSON is missing for the year is
 * simply not offered for live calculation, so the report shows it as pending
 * instead of failing.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Reporting\Clinical;

final readonly class EcqmMeasureCatalog
{
    public function __construct(private string $measuresBasePath)
    {
    }

    /**
     * The measure directories installed for the year, for the mapped measures
     * that have a resolved population-set selection.
     *
     * @return array<string, string> measure directory path keyed by CMS eCQM
     *     id (UdsClinicalMeasure::cmsId())
     */
    public function installedMeasurePaths(int $year): array
    {
        $yearDirectory = $this->measuresBasePath . '/' . $year . '_reporting_period/json_measures';

        $paths = [];
        foreach (UdsClinicalMeasure::cases() as $measure) {
            if ($measure->reportedPopulationSetId() === null) {
                continue;
            }

            $measureDirectory = $yearDirectory . '/' . $measure->cmsId();
            $measureJson = $measureDirectory . '/' . $measure->cmsId() . '.json';
            $valueSetsJson = $measureDirectory . '/value_sets.json';
            if (is_file($measureJson) && is_file($valueSetsJson)) {
                $paths[$measure->cmsId()] = $measureDirectory;
            }
        }

        return $paths;
    }
}
