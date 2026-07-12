<?php

/**
 * One due clinical screening/reminder for a patient on the rooming worklist
 * (issue #37), already labeled by the CDR engine's own list translations at
 * the entry point.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Rooming;

final readonly class ScreeningDue
{
    public function __construct(
        public string $label,
        public ScreeningDueStatus $status,
    ) {
        if ($label === '') {
            throw new \DomainException('Screening label must not be empty');
        }
    }
}
