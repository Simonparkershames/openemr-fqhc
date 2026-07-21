<?php

/**
 * CqmCalculationEngine backed by OpenEMR's real eCQM pipeline: QdmBuilder
 * builds the QDM patient models, the node cqm-execution service scores each
 * measure, and ResultsCalculator aggregates the per-patient results into
 * population counts — the same flow the QRDA Category III export uses
 * (src/Services/Qrda/ExportCat3Service), stopped at the aggregate hash
 * instead of rendering XML.
 *
 * Nothing in src/Cqm or src/Services/Qdm is modified: this adapter only
 * composes their public APIs, so the certified QRDA path is untouched. The
 * measure files are read from the explicit paths the catalog resolved, and
 * the performance period comes from the requested UDS reporting year — not
 * from the cqm_performance_period global.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Reporting\Clinical;

use OpenEMR\Cqm\CqmClient;
use OpenEMR\Cqm\CqmServiceManager;
use OpenEMR\Services\Qdm\CqmCalculator;
use OpenEMR\Services\Qdm\IndividualResult;
use OpenEMR\Services\Qdm\Measure;
use OpenEMR\Services\Qdm\MeasureService;
use OpenEMR\Services\Qdm\QdmBuilder;
use OpenEMR\Services\Qdm\QdmRequestAll;
use OpenEMR\Services\Qdm\ResultsCalculator;

final class CqmExecutionServiceEngine implements CqmCalculationEngine
{
    public function aggregatePopulationCounts(array $measurePathsByCmsId, int $year): array
    {
        if ($measurePathsByCmsId === []) {
            return [];
        }

        $this->ensureCalculationServiceIsRunning();

        $effectiveDate = $year . '-01-01 00:00:00';
        $effectiveDateEnd = $year . '-12-31 23:59:59';

        $measuresByCmsId = $this->loadMeasures($measurePathsByCmsId);
        $patients = (new QdmBuilder())->build(new QdmRequestAll());
        $calculator = new CqmCalculator();

        $individualResultsByHqmfId = [];
        foreach ($measuresByCmsId as $measure) {
            $response = $calculator->calculateMeasure($patients, $measure, $effectiveDate, $effectiveDateEnd);
            $individualResultsByHqmfId[(string) $measure->hqmf_id] = $this->individualResults($response, $measure);
        }

        $aggregatesByHqmfId = (new ResultsCalculator($patients, '', $effectiveDate))
            ->aggregate_results_for_measures(array_values($measuresByCmsId), $individualResultsByHqmfId);
        if (!is_array($aggregatesByHqmfId)) {
            throw new \RuntimeException('CQM results aggregation returned an unusable result');
        }

        $aggregatesByCmsId = [];
        foreach ($measuresByCmsId as $cmsId => $measure) {
            $aggregate = $aggregatesByHqmfId[(string) $measure->hqmf_id] ?? null;
            if (!is_array($aggregate)) {
                continue;
            }
            $byPopulationSetKey = [];
            foreach ($aggregate as $populationSetKey => $populations) {
                if (is_string($populationSetKey)) {
                    $byPopulationSetKey[$populationSetKey] = $populations;
                }
            }
            $aggregatesByCmsId[$cmsId] = $byPopulationSetKey;
        }

        return $aggregatesByCmsId;
    }

    /**
     * @param array<string, string> $measurePathsByCmsId
     * @return array<string, Measure> keyed by CMS eCQM id
     */
    private function loadMeasures(array $measurePathsByCmsId): array
    {
        $measuresByCmsId = [];
        foreach ($measurePathsByCmsId as $cmsId => $measurePath) {
            $definition = MeasureService::fetchMeasureJson($measurePath);
            if (!is_array($definition)) {
                throw new \RuntimeException('Could not load the measure definition for ' . $cmsId);
            }
            $measure = new Measure($definition);
            $measure->measure_path = $measurePath;
            $measuresByCmsId[$cmsId] = $measure;
        }

        return $measuresByCmsId;
    }

    /**
     * Wraps the calculation service's per-patient response for one measure
     * into the IndividualResult objects ResultsCalculator aggregates — the
     * same shaping the QRDA Category III export applies.
     *
     * @return list<IndividualResult>
     */
    private function individualResults(mixed $response, Measure $measure): array
    {
        if (!is_array($response)) {
            throw new \RuntimeException('CQM calculation service returned an unusable result');
        }

        $individualResults = [];
        foreach ($response as $patientId => $populationSetResults) {
            if (!is_array($populationSetResults)) {
                throw new \RuntimeException('CQM calculation service returned an unusable patient result');
            }
            foreach ($populationSetResults as $populationSetKey => $individualResult) {
                if (!is_array($individualResult)) {
                    throw new \RuntimeException('CQM calculation service returned an unusable population result');
                }
                $individualResult['population_set_key'] = $populationSetKey;
                $individualResult['patient_id'] = $patientId;
                $individualResults[] = new IndividualResult($individualResult, $measure);
            }
        }

        return $individualResults;
    }

    /**
     * Starts the node cqm-execution service if it is not already up, the same
     * way QrdaReportService does before an export.
     */
    private function ensureCalculationServiceIsRunning(): void
    {
        $client = CqmServiceManager::makeCqmClient();
        if ($this->serviceIsUp($client)) {
            return;
        }

        $client->start();
        sleep(2);
        if (!$this->serviceIsUp($client)) {
            throw new \RuntimeException('The CQM calculation node service is not running');
        }
    }

    private function serviceIsUp(CqmClient $client): bool
    {
        $uptime = $client->getHealth()['uptime'] ?? null;

        return is_numeric($uptime) && (float) $uptime > 0.0;
    }
}
