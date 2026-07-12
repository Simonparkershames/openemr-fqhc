<?php

/**
 * A single slot on the demo clinic's schedule for "today".
 *
 * Ties a start time and check-in state to a provider (referenced by demo
 * username, resolved to a `users.id` at seed time) so the seeded schedule spans
 * several providers across the morning. Pure value object.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Demo;

final readonly class DemoAppointment
{
    /**
     * @param string $providerUsername demo username of the rendering provider
     * @param string $startTime        24-hour "HH:MM" local clinic time
     * @param int    $durationMinutes  slot length
     */
    public function __construct(
        public string $providerUsername,
        public string $startTime,
        public int $durationMinutes,
        public DemoAppointmentStatus $status,
        public string $reason,
    ) {
    }
}
