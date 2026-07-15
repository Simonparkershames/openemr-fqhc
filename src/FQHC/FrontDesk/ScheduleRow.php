<?php

/**
 * One raw appointment row for a front-desk day, already typed at the SQL
 * boundary (issue #36).
 *
 * The repository parses the calendar/patient/insurance join into this shape;
 * the pure day builder turns it into the view-ready FrontDeskAppointment.
 * Keeping the two apart means every classification and readiness rule is
 * testable without a database.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\FrontDesk;

final readonly class ScheduleRow
{
    public function __construct(
        public int $eventId,
        public int $pid,
        public string $firstName,
        public string $lastName,
        public ?string $sexCode,
        public ?string $dateOfBirth,
        public string $startTime,
        public int $durationSeconds,
        public int $providerId,
        public string $providerName,
        public string $categoryName,
        public string $statusCode,
        public string $statusTitle,
        public bool $hasInsuranceOnFile,
        public bool $hasIncomeDetermination,
    ) {
        if ($eventId <= 0) {
            throw new \DomainException('Appointment event id must be positive');
        }
        if ($pid <= 0) {
            throw new \DomainException('Appointment patient id must be positive');
        }
    }
}
