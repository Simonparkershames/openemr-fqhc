<?php

/**
 * Assembles the provider's care-gap panel from per-patient screening lists
 * (issue #38).
 *
 * Pure and deterministic: the entry point runs the CDR engine for each
 * patient on the provider's panel and passes in the typed screenings; this
 * builder attaches each gap to its patient and orders the panel by urgency
 * (past due, then due, then due soon), preserving the caller's patient order
 * within a status so the list is stable. Keeping this out of the SQL/engine
 * boundary means the ordering rule is testable in isolation.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Provider;

use OpenEMR\FQHC\Rooming\ScreeningDue;
use OpenEMR\FQHC\Rooming\ScreeningDueStatus;

final readonly class CareGapPanelBuilder
{
    private const STATUS_ORDER = [
        ScreeningDueStatus::PastDue->value => 0,
        ScreeningDueStatus::Due->value => 1,
        ScreeningDueStatus::SoonDue->value => 2,
    ];

    /**
     * @param list<PanelPatient> $patients the provider's panel, in display order
     * @param array<int, list<ScreeningDue>> $screeningsByPid due screenings keyed by pid
     * @return list<CareGap>
     */
    public function build(array $patients, array $screeningsByPid): array
    {
        $gaps = [];
        foreach ($patients as $order => $patient) {
            foreach ($screeningsByPid[$patient->pid] ?? [] as $screening) {
                $gaps[] = [
                    'order' => $order,
                    'gap' => new CareGap($patient->pid, $patient->name, $screening),
                ];
            }
        }

        usort(
            $gaps,
            static fn(array $a, array $b): int
                => [self::rank($a['gap']), $a['order']] <=> [self::rank($b['gap']), $b['order']],
        );

        return array_map(static fn(array $entry): CareGap => $entry['gap'], $gaps);
    }

    private static function rank(CareGap $gap): int
    {
        return self::STATUS_ORDER[$gap->screening->status->value];
    }
}
