<?php

/**
 * Parses the CDR engine's raw action arrays (test_rules_clinic() output)
 * into typed ScreeningDue values (issue #37).
 *
 * Pure: the entry point resolves the human-readable label through the
 * engine's own list translations and passes it in; this factory owns the
 * filtering rules — only concrete, actionable reminders make the rooming
 * worklist (plans and not-yet-due items are dropped, as are unlabeled
 * actions).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Rooming;

final readonly class ScreeningDueFactory
{
    /**
     * @param array<mixed> $action one action array from test_rules_clinic()
     */
    public function fromRuleAction(array $action, string $label): ?ScreeningDue
    {
        $label = trim($label);
        if ($label === '') {
            return null;
        }

        $isPlan = $action['is_plan'] ?? null;
        if (!in_array($isPlan, [null, '', '0', 0, false], true)) {
            return null;
        }

        $dueStatus = $action['due_status'] ?? null;
        if (!is_string($dueStatus)) {
            return null;
        }

        $status = ScreeningDueStatus::tryFrom($dueStatus);
        if ($status === null) {
            return null;
        }

        return new ScreeningDue($label, $status);
    }
}
