<?php

/**
 * An arrival-readiness gap front desk should close at check-in (issue #36).
 *
 * These reuse the same underlying data the UDS data-quality worklist reads
 * (patient demographics, insurance on file, the FQHC income determination),
 * surfaced per-appointment so the gap is closed while the patient is at the
 * desk instead of during year-end report cleanup.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\FrontDesk;

enum ReadinessFlag
{
    case MissingDateOfBirth;
    case MissingSex;
    case NoInsuranceOnFile;
    case NoIncomeDetermination;

    public function label(): string
    {
        return match ($this) {
            self::MissingDateOfBirth => 'DOB missing',
            self::MissingSex => 'Sex missing',
            self::NoInsuranceOnFile => 'No insurance on file',
            self::NoIncomeDetermination => 'Sliding fee: income not on file',
        };
    }
}
