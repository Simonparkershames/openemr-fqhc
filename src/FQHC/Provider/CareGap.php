<?php

/**
 * One open care gap on the provider's panel (issue #38): a due clinical
 * reminder — screening, immunization, or a UDS clinical-quality measure —
 * attached to the patient it belongs to, so the provider can close it during
 * the visit. This is where the experience story meets the compliance story:
 * the same reminders that drive the UDS clinical tables surface here as work
 * the provider can act on.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Provider;

use OpenEMR\FQHC\Rooming\ScreeningDue;

final readonly class CareGap
{
    public function __construct(
        public int $pid,
        public string $patientName,
        public ScreeningDue $screening,
    ) {
    }
}
