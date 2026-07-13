<?php

/**
 * Database boundary for the rooming clinical glance (issue #37): a
 * patient's active allergies and medications from the certified `lists`
 * table, using the same "activity = 1 and enddate empty or future" activity
 * test the CDR engine uses (library/clinical_rules.php allergy_conflict()).
 * Screenings due come from the CDR engine at the entry point, not here.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Rooming;

use OpenEMR\Common\Database\QueryUtils;

final readonly class PatientGlanceRepository
{
    /**
     * @return list<string>
     */
    public function activeAllergyTitles(int $pid): array
    {
        return $this->activeListTitles('allergy', $pid);
    }

    /**
     * @return list<string>
     */
    public function activeMedicationTitles(int $pid): array
    {
        return $this->activeListTitles('medication', $pid);
    }

    /**
     * @return list<string>
     */
    private function activeListTitles(string $type, int $pid): array
    {
        if ($pid <= 0) {
            return [];
        }

        $records = QueryUtils::fetchRecords(
            'SELECT title FROM lists WHERE type = ? AND pid = ? AND activity = 1 '
            . "AND (enddate IS NULL OR enddate = '0000-00-00' OR enddate > NOW()) "
            . 'ORDER BY title',
            [$type, $pid],
        );

        $titles = [];
        foreach ($records as $record) {
            $title = $record['title'] ?? null;
            if (is_string($title) && trim($title) !== '') {
                $titles[] = trim($title);
            }
        }

        return $titles;
    }
}
