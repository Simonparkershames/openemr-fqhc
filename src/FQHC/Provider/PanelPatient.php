<?php

/**
 * A patient on the provider's panel for the day (issue #38): the minimal
 * identity the care-gap panel needs to attach a due reminder to a person and
 * link to their chart. The panel is the set of patients on the provider's
 * schedule, in display order.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Provider;

final readonly class PanelPatient
{
    public function __construct(
        public int $pid,
        public string $name,
    ) {
    }
}
