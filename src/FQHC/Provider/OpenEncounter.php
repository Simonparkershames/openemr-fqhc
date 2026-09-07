<?php

/**
 * A visit the provider has started but not yet finished documenting
 * (issue #38): an encounter opened today for one of their patients that is
 * still awaiting the note. One click from here lands in the encounter.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Provider;

final readonly class OpenEncounter
{
    public function __construct(
        public int $encounterId,
        public int $pid,
        public string $patientName,
        public string $timeDisplay,
        public string $reason,
    ) {
    }
}
