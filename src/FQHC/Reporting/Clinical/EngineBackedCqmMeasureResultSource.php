<?php

/**
 * CqmMeasureResultSource that reports live population counts from the CQM
 * engine — the wiring PendingCqmMeasureResultSource was holding the door
 * open for.
 *
 * For a reporting year it asks the catalog which mapped measures are
 * installed, runs them through the calculation engine, and parses each
 * measure's selected population set into UDS population counts. Coverage is
 * honest by construction: a measure that is not installed for the year, has
 * no resolved population-set selection, or comes back malformed simply has
 * no entry — the report shows it as pending, never as a guessed number. An
 * engine failure (node service down, unusable response) is logged and
 * degrades the whole table to pending rather than breaking the UDS report
 * page, whose patient tables do not depend on the engine.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Reporting\Clinical;

use Psr\Log\LoggerInterface;

final readonly class EngineBackedCqmMeasureResultSource implements CqmMeasureResultSource
{
    private CqmAggregateResultParser $parser;

    public function __construct(
        private EcqmMeasureCatalog $catalog,
        private CqmCalculationEngine $engine,
        private LoggerInterface $logger,
        ?CqmAggregateResultParser $parser = null,
    ) {
        $this->parser = $parser ?? new CqmAggregateResultParser();
    }

    public function resultsForYear(int $year): array
    {
        $measurePathsByCmsId = $this->catalog->installedMeasurePaths($year);
        if ($measurePathsByCmsId === []) {
            return [];
        }

        try {
            $aggregatesByCmsId = $this->engine->aggregatePopulationCounts($measurePathsByCmsId, $year);
        } catch (\Throwable $exception) {
            $this->logger->error('UDS Table 6B live CQM calculation failed; measures remain pending', [
                'year' => $year,
                'exception' => $exception,
            ]);

            return [];
        }

        $results = [];
        foreach (UdsClinicalMeasure::cases() as $measure) {
            $aggregate = $aggregatesByCmsId[$measure->cmsId()] ?? null;
            if ($aggregate === null) {
                continue;
            }
            $counts = $this->parser->countsFor($measure, $aggregate);
            if ($counts !== null) {
                $results[$measure->cmsId()] = $counts;
            }
        }

        return $results;
    }
}
