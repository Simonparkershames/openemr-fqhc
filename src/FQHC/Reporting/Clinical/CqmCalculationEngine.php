<?php

/**
 * Runs the CQM engine over the practice's patients and returns its aggregate
 * population counts for a set of installed measures.
 *
 * Abstracting the calculation behind this interface keeps
 * EngineBackedCqmMeasureResultSource's orchestration (which measures to
 * request, how to interpret the aggregates, how to degrade when the engine
 * fails) unit-testable without a database or the node calculation service.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Reporting\Clinical;

interface CqmCalculationEngine
{
    /**
     * @param array<string, string> $measurePathsByCmsId measure directory
     *     path keyed by CMS eCQM id (from EcqmMeasureCatalog)
     * @return array<string, array<string, mixed>> the engine's aggregate
     *     results keyed by CMS eCQM id, then by population-set key (the
     *     ResultsCalculator hash for that measure); a measure absent from
     *     the result could not be calculated
     * @throws \RuntimeException when the engine cannot complete the
     *     calculation at all (service down, unusable response)
     */
    public function aggregatePopulationCounts(array $measurePathsByCmsId, int $year): array;
}
