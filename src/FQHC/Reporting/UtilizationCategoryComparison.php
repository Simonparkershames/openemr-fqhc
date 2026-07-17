<?php

/**
 * One UDS Table 5 service line compared across two reporting years: its total
 * visits (Column B + B2) this year and last, and the year-over-year change.
 *
 * A pure value object feeding the manager/quality workspace utilization
 * snapshot (issue #39). Visits — not patients — are compared: Table 5 patient
 * counts duplicate across categories, so summing them is not a health-center
 * total, whereas visits are additive and are the utilization signal a manager
 * watches season to season.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Reporting;

final readonly class UtilizationCategoryComparison
{
    public function __construct(
        public UdsServiceCategory $category,
        public int $currentVisits,
        public int $priorVisits,
    ) {
    }

    public function delta(): int
    {
        return $this->currentVisits - $this->priorVisits;
    }

    /**
     * Whether the category saw any visits in either year — the snapshot hides
     * service lines the center does not run so it stays a summary, not a
     * seven-row table of zeros.
     */
    public function hasActivity(): bool
    {
        return $this->currentVisits > 0 || $this->priorVisits > 0;
    }
}
