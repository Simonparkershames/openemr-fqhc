<?php

/**
 * A view-ready appointment on the front-desk day list (issue #36): who is
 * coming, when, with whom, where they are in the arrival loop, and which
 * arrival-readiness gaps to close at the desk.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\FrontDesk;

final readonly class FrontDeskAppointment
{
    /**
     * @param list<ReadinessFlag> $readinessFlags
     */
    public function __construct(
        public int $eventId,
        public int $pid,
        public string $patientName,
        public string $timeDisplay,
        public string $startTime,
        public int $durationMinutes,
        public int $providerId,
        public string $providerName,
        public string $categoryName,
        public string $statusTitle,
        public AppointmentPhase $phase,
        public array $readinessFlags,
    ) {
    }

    public function isReady(): bool
    {
        return $this->readinessFlags === [];
    }
}
