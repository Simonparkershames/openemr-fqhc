<?php

/**
 * A lab/diagnostic report awaiting the provider's review (issue #38): a
 * result that has come back for a test this provider ordered and has not yet
 * been signed off. Abnormal reports are flagged so they stand out in the
 * inbox.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Provider;

final readonly class ResultToReview
{
    public function __construct(
        public int $pid,
        public string $patientName,
        public string $testName,
        public string $reportedDisplay,
        public bool $isAbnormal,
    ) {
    }
}
