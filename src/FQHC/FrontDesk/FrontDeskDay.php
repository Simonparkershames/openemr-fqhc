<?php

/**
 * The front-desk view of one clinic day (issue #36): the time-ordered
 * appointment list plus the counts the desk watches at a glance.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\FrontDesk;

final readonly class FrontDeskDay
{
    /**
     * @param list<FrontDeskAppointment> $appointments time-ordered
     */
    public function __construct(
        public string $date,
        public array $appointments,
    ) {
    }

    public function total(): int
    {
        return count($this->appointments);
    }

    public function countInPhase(AppointmentPhase $phase): int
    {
        return count(array_filter(
            $this->appointments,
            static fn(FrontDeskAppointment $appointment): bool => $appointment->phase === $phase,
        ));
    }

    /**
     * Appointments still live for the desk (expected or in the building)
     * that carry an arrival-readiness gap to close at check-in.
     */
    public function needsAttentionCount(): int
    {
        return count(array_filter(
            $this->appointments,
            static fn(FrontDeskAppointment $appointment): bool => $appointment->phase->isActive()
                && !$appointment->isReady(),
        ));
    }
}
