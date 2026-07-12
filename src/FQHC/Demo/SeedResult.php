<?php

/**
 * The tally a demo seed run reports back.
 *
 * A seed is idempotent, so each entity is either created this run or skipped
 * because it already existed; the counts let the CLI print a clear "created N,
 * skipped M" summary and let a caller assert the run did something. Warnings
 * collect non-fatal problems (e.g. a provider account that could not be
 * created, so its appointments were left unassigned) without aborting the run.
 *
 * Mutable by design: the seeder accumulates into one instance as it walks the
 * panel. Not a value object.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Demo;

final class SeedResult
{
    public int $usersCreated = 0;
    public int $usersSkipped = 0;
    public int $patientsCreated = 0;
    public int $patientsSkipped = 0;
    public int $appointmentsCreated = 0;
    public int $encountersCreated = 0;
    public int $insuranceRowsCreated = 0;

    /** @var list<string> */
    public array $warnings = [];

    public function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    /**
     * @return array<string, int|list<string>>
     */
    public function toArray(): array
    {
        return [
            'usersCreated' => $this->usersCreated,
            'usersSkipped' => $this->usersSkipped,
            'patientsCreated' => $this->patientsCreated,
            'patientsSkipped' => $this->patientsSkipped,
            'appointmentsCreated' => $this->appointmentsCreated,
            'encountersCreated' => $this->encountersCreated,
            'insuranceRowsCreated' => $this->insuranceRowsCreated,
            'warnings' => $this->warnings,
        ];
    }
}
