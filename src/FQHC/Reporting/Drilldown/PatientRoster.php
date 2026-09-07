<?php

/**
 * The patients contributing to each cell of a UDS report, keyed by an opaque
 * cell key.
 *
 * A report table exposes totals; this is the parallel roster that answers
 * "which patients are behind this number?" for the drill-down. It carries only
 * patient ids — names are resolved at the boundary — so it stays a pure value
 * object built alongside the counts, and the drill-down count for any cell is
 * always exactly the report count for that cell (the roster and the aggregator
 * consume the same per-patient records).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Reporting\Drilldown;

final readonly class PatientRoster
{
    /**
     * @param array<string, list<int>> $pidsByCell patient ids keyed by cell key
     */
    public function __construct(private array $pidsByCell)
    {
    }

    /**
     * The patient ids behind a cell, in the order they were tallied.
     *
     * @return list<int>
     */
    public function pidsFor(string $cell): array
    {
        return $this->pidsByCell[$cell] ?? [];
    }

    public function countFor(string $cell): int
    {
        return count($this->pidsByCell[$cell] ?? []);
    }

    /**
     * Every distinct patient id referenced by any cell, ascending — the set of
     * ids whose names the boundary needs to resolve for the drill-down.
     *
     * @return list<int>
     */
    public function allPids(): array
    {
        $seen = [];
        foreach ($this->pidsByCell as $pids) {
            foreach ($pids as $pid) {
                $seen[$pid] = true;
            }
        }

        $ids = array_keys($seen);
        sort($ids);

        return $ids;
    }
}
