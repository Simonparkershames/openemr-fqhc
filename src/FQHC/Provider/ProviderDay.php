<?php

/**
 * The logged-in provider's schedule for one day (issue #38): the visits
 * assigned to them, in time order, each carrying its live arrival/rooming
 * status. The count helpers drive the header chips (scheduled, in a room,
 * seen) that let a provider gauge their day at a glance.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Provider;

use OpenEMR\FQHC\FrontDesk\AppointmentPhase;

final readonly class ProviderDay
{
    /**
     * @param list<ProviderScheduleEntry> $entries
     */
    public function __construct(
        public string $date,
        public array $entries,
    ) {
    }

    public function total(): int
    {
        return count($this->entries);
    }

    public function readyCount(): int
    {
        return $this->countPhase(AppointmentPhase::WithCareTeam);
    }

    public function arrivedCount(): int
    {
        return $this->countPhase(AppointmentPhase::Arrived);
    }

    public function checkedOutCount(): int
    {
        return $this->countPhase(AppointmentPhase::CheckedOut);
    }

    private function countPhase(AppointmentPhase $phase): int
    {
        $count = 0;
        foreach ($this->entries as $entry) {
            if ($entry->appointment->phase === $phase) {
                $count++;
            }
        }

        return $count;
    }
}
