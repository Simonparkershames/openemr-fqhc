<?php

/**
 * Builds the provider's day from the shared front-desk day view (issue #38).
 *
 * Pure and deterministic: it keeps only the appointments assigned to the
 * given provider (matched on the calendar's provider id) and attaches the
 * rooming context — room label and open encounter — supplied by the
 * repositories. Time ordering is inherited from the front-desk day, so the
 * provider's list reads top-to-bottom through the clinic day. No database
 * access lives here, so every filtering and attachment rule is testable in
 * isolation.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Provider;

use OpenEMR\FQHC\FrontDesk\FrontDeskDay;

final readonly class ProviderDayBuilder
{
    /**
     * @param array<int, string> $roomLabelByEventId
     * @param array<int, int> $encounterByEventId
     */
    public function build(
        FrontDeskDay $day,
        int $providerId,
        array $roomLabelByEventId,
        array $encounterByEventId,
    ): ProviderDay {
        $entries = [];
        foreach ($day->appointments as $appointment) {
            if ($appointment->providerId !== $providerId) {
                continue;
            }

            $entries[] = new ProviderScheduleEntry(
                $appointment,
                $roomLabelByEventId[$appointment->eventId] ?? null,
                $encounterByEventId[$appointment->eventId] ?? null,
            );
        }

        return new ProviderDay($day->date, $entries);
    }
}
