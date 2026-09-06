<?php

/**
 * Checks the Table 6B clinical denominators against the patient tables'
 * unduplicated patient universe.
 *
 * An eCQM denominator counts patients with qualifying encounters in the
 * measurement period, so no measure's denominator should exceed the
 * unduplicated patient count the UDS patient tables report for the same
 * year. When one does, the cohorts have diverged — the engine is counting
 * patients the UDS visit rules do not (or the patient tables are dropping
 * patients they should count) — and the report needs review before
 * submission. Like CrossTableReconciliation, this is a pure data-quality
 * guard over already-computed reports; it recounts nothing.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Reporting\Clinical;

final class Table6bReconciliation
{
    /**
     * The computed measures whose full denominator (before exclusions and
     * exceptions) exceeds the unduplicated patient count.
     *
     * @return list<UdsClinicalMeasure>
     */
    public function measuresExceedingPatientUniverse(Table6bReport $report, int $unduplicatedPatients): array
    {
        $exceeding = [];
        foreach (UdsClinicalMeasure::cases() as $measure) {
            $result = $report->resultFor($measure);
            if ($result !== null && $result->denominator > $unduplicatedPatients) {
                $exceeding[] = $measure;
            }
        }

        return $exceeding;
    }
}
