<?php

/**
 * How overdue a clinical reminder is (issue #37), parsed from the CDR
 * engine's `due_status` values. Only the actionable states appear on the
 * rooming worklist — `not_due` items are filtered out at the boundary.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Rooming;

enum ScreeningDueStatus: string
{
    case PastDue = 'past_due';
    case Due = 'due';
    case SoonDue = 'soon_due';

    public function label(): string
    {
        return match ($this) {
            self::PastDue => 'Past due',
            self::Due => 'Due',
            self::SoonDue => 'Due soon',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::PastDue => 'danger',
            self::Due => 'warning',
            self::SoonDue => 'neutral',
        };
    }
}
