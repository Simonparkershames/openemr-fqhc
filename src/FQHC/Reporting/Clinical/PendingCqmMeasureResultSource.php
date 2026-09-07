<?php

/**
 * Null-object CqmMeasureResultSource: reports every UDS clinical measure as
 * not yet computed.
 *
 * Production wires EngineBackedCqmMeasureResultSource, which pulls live
 * population counts from the CQM engine for the population set each measure
 * selects (UdsClinicalMeasure::reportedPopulationSetId()). This
 * implementation remains the honest "nothing computed" state for tests and
 * for callers that must render Table 6B/7 without touching the engine.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Reporting\Clinical;

final class PendingCqmMeasureResultSource implements CqmMeasureResultSource
{
    public function resultsForYear(int $year): array
    {
        return [];
    }
}
