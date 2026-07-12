<?php

/**
 * One patient on the MA rooming worklist (issue #37): the appointment facts
 * from the front-desk day view plus the rooming context (room, open
 * encounter) and the clinical glance.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Rooming;

use OpenEMR\FQHC\FrontDesk\FrontDeskAppointment;

final readonly class RoomingQueueEntry
{
    public function __construct(
        public FrontDeskAppointment $appointment,
        public ?string $roomLabel,
        public ?int $encounterId,
        public PatientGlance $glance,
    ) {
    }
}
