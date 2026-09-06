<?php

/**
 * A year-over-year utilization comparison for the manager/quality workspace
 * (issue #39): the reporting year's UDS Table 5 visits set against the prior
 * year's, in total and per service line.
 *
 * Pure and deterministic — it derives everything from two already-computed
 * Table 5 reports, so it is unit-testable without a database. The manager
 * home renders it as an at-a-glance "are we seeing more or fewer patients
 * than last year?" snapshot above the report shortcuts.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Reporting;

final readonly class UtilizationComparison
{
    public function __construct(
        public int $year,
        public int $priorYear,
        private Table5Report $current,
        private Table5Report $prior,
    ) {
    }

    public function currentVisits(): int
    {
        return $this->current->grandTotalVisits();
    }

    public function priorVisits(): int
    {
        return $this->prior->grandTotalVisits();
    }

    public function visitsDelta(): int
    {
        return $this->currentVisits() - $this->priorVisits();
    }

    /**
     * Whether either year recorded any visit at all — the home shows an
     * empty-state note instead of the snapshot when the center has no
     * countable utilization yet (a fresh install, or before the demo seed).
     */
    public function hasActivity(): bool
    {
        return $this->currentVisits() > 0 || $this->priorVisits() > 0;
    }

    /**
     * Per-service-line comparisons in UDS Table 5 order, including lines with
     * no activity — callers filter with UtilizationCategoryComparison::hasActivity().
     *
     * @return list<UtilizationCategoryComparison>
     */
    public function categories(): array
    {
        $rows = [];
        foreach (UdsServiceCategory::cases() as $category) {
            $rows[] = new UtilizationCategoryComparison(
                $category,
                $this->current->totalVisits($category),
                $this->prior->totalVisits($category),
            );
        }

        return $rows;
    }
}
