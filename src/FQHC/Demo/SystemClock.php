<?php

/**
 * The wall-clock, as a PSR-20 clock.
 *
 * The seeder dates the reporting-year visits and today's schedule relative to
 * "now", and the rest of the code should not reach for the system clock
 * directly (so it stays testable). This is the single, deliberate place that
 * reads real time; a test can substitute any other ClockInterface.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Demo;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
