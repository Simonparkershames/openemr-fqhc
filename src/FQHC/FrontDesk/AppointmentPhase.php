<?php

/**
 * The coarse front-desk phase of a day's appointment (issue #36).
 *
 * OpenEMR stores appointment status as site-configurable single-character
 * codes from the certified `apptstat` list. Front desk does not care about
 * every code — it cares where the patient is in the arrival loop: still
 * expected, waiting after check-in, with the care team, done, blocked on an
 * insurance/financial issue, or not coming at all. The classifier maps codes
 * to these phases; unknown site-added codes fall back to Expected so a
 * customized status list never hides an appointment.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\FrontDesk;

enum AppointmentPhase
{
    case Expected;
    case Arrived;
    case WithCareTeam;
    case CheckedOut;
    case FinancialIssue;
    case NotComing;

    public function label(): string
    {
        return match ($this) {
            self::Expected => 'Expected',
            self::Arrived => 'Arrived',
            self::WithCareTeam => 'With care team',
            self::CheckedOut => 'Checked out',
            self::FinancialIssue => 'Ins/financial issue',
            self::NotComing => 'Cancelled / no-show',
        };
    }

    /**
     * The fqhc-status-badge variant this phase renders with
     * (success | warning | danger | info | neutral).
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Expected => 'neutral',
            self::Arrived => 'warning',
            self::WithCareTeam => 'info',
            self::CheckedOut => 'success',
            self::FinancialIssue => 'danger',
            self::NotComing => 'neutral',
        };
    }

    /**
     * Whether the visit is still live for front desk — i.e. the patient is
     * expected or in the building, so arrival-readiness gaps still matter.
     */
    public function isActive(): bool
    {
        return match ($this) {
            self::Expected, self::Arrived, self::WithCareTeam, self::FinancialIssue => true,
            self::CheckedOut, self::NotComing => false,
        };
    }
}
