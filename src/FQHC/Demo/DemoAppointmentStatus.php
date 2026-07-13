<?php

/**
 * A demo appointment's check-in state, mapped to OpenEMR's `apptstat` codes.
 *
 * The calendar stores appointment status as the single-character option_ids from
 * the certified `apptstat` list. A living demo schedule needs patients at
 * different points in the day's flow — still expected, checked in at the desk,
 * and moved into an exam room — so the front-desk, MA, and provider workspaces
 * each have real content on first login.
 *
 * Backed by the stored `pc_apptstatus` code; matched exhaustively so a new state
 * forces a decision about whether it opens an encounter.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Demo;

enum DemoAppointmentStatus: string
{
    case Scheduled = '-';
    case Arrived = '@';
    case Roomed = '<';

    /**
     * Whether a patient in this state has an open encounter for the visit. A
     * patient who has arrived or been roomed has been checked in and has a
     * started encounter; a merely scheduled patient does not yet.
     */
    public function opensEncounter(): bool
    {
        return match ($this) {
            self::Arrived, self::Roomed => true,
            self::Scheduled => false,
        };
    }
}
