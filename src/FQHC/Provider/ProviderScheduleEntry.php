<?php

/**
 * One visit on the provider's day (issue #38): the appointment facts from the
 * shared front-desk day view, plus where the patient is right now — the room
 * they were taken to and the open encounter the tracker mapped to the visit,
 * so the provider can jump straight into the note.
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
use OpenEMR\FQHC\FrontDesk\FrontDeskAppointment;

final readonly class ProviderScheduleEntry
{
    public function __construct(
        public FrontDeskAppointment $appointment,
        public ?string $roomLabel,
        public ?int $encounterId,
    ) {
    }

    /**
     * The patient is in an exam room (or otherwise with the care team) and
     * ready for the provider — the visits the provider acts on first.
     */
    public function isReadyForProvider(): bool
    {
        return $this->appointment->phase === AppointmentPhase::WithCareTeam;
    }
}
