<?php

/**
 * Maps certified `apptstat` codes to front-desk phases (issue #36).
 *
 * Pure and deterministic. The code set mirrors the certified seed list in
 * sql/database.sql; sites can add their own codes, so anything unrecognized
 * classifies as Expected — the safe reading that keeps the appointment
 * visible on the day list rather than silently dropping it.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\FrontDesk;

final readonly class AppointmentStatusClassifier
{
    public function classify(string $statusCode): AppointmentPhase
    {
        return match ($statusCode) {
            '@', '~' => AppointmentPhase::Arrived,
            '<' => AppointmentPhase::WithCareTeam,
            '>', '$' => AppointmentPhase::CheckedOut,
            '#' => AppointmentPhase::FinancialIssue,
            'x', '%', '?', '!' => AppointmentPhase::NotComing,
            default => AppointmentPhase::Expected,
        };
    }
}
