<?php

/**
 * Extracts one UDS line's population counts from the CQM engine's aggregate
 * results for a measure.
 *
 * The engine (src/Services/Qdm/ResultsCalculator) emits, per measure, a hash
 * keyed by population-set key whose entries carry the proportion-measure
 * population totals (IPP, DENOM, DENEX, DENEXCEP, NUMER). This parser reads
 * exactly the population set the UDS measure map selects
 * (UdsClinicalMeasure::reportedPopulationSetId()) and refuses everything
 * else: an unresolved selection, a missing population set, or a malformed
 * count yields null — the measure stays visibly pending rather than report a
 * number that might be wrong.
 *
 * Pure and deterministic, so the population-set selection is testable without
 * the engine.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Reporting\Clinical;

final class CqmAggregateResultParser
{
    /**
     * @param array<string, mixed> $aggregateByPopulationSetKey the engine's
     *     aggregate hash for one measure, keyed by population-set key
     */
    public function countsFor(UdsClinicalMeasure $measure, array $aggregateByPopulationSetKey): ?UdsMeasurePopulationCounts
    {
        $populationSetId = $measure->reportedPopulationSetId();
        if ($populationSetId === null) {
            return null;
        }

        $populations = $aggregateByPopulationSetKey[$populationSetId] ?? null;
        if (!is_array($populations)) {
            return null;
        }

        $initialPopulation = $this->count($populations, 'IPP');
        $denominator = $this->count($populations, 'DENOM');
        $denominatorExclusions = $this->count($populations, 'DENEX');
        $denominatorExceptions = $this->count($populations, 'DENEXCEP');
        $numerator = $this->count($populations, 'NUMER');
        if (
            $initialPopulation === null
            || $denominator === null
            || $denominatorExclusions === null
            || $denominatorExceptions === null
            || $numerator === null
        ) {
            return null;
        }

        return new UdsMeasurePopulationCounts(
            initialPopulation: $initialPopulation,
            denominator: $denominator,
            denominatorExclusions: $denominatorExclusions,
            denominatorExceptions: $denominatorExceptions,
            numerator: $numerator,
        );
    }

    /**
     * A population total from the engine hash: a missing key is a population
     * the measure does not define (zero), while a present-but-non-integer
     * value is malformed engine output and poisons the whole result.
     *
     * @param array<mixed, mixed> $populations
     */
    private function count(array $populations, string $populationKey): ?int
    {
        if (!array_key_exists($populationKey, $populations)) {
            return 0;
        }

        $value = $populations[$populationKey];
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }

        return null;
    }
}
