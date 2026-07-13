<?php

/**
 * Raised when the demo seeder cannot complete a step it cannot recover from,
 * such as a missing facility to attach visits to or a failed patient insert.
 *
 * Non-fatal problems (an unmapped payer, an uncreatable provider account) are
 * collected as warnings on the {@see SeedResult} instead, so a single bad row
 * does not abort the whole clinic.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Demo;

use RuntimeException;

final class DemoSeedException extends RuntimeException
{
}
