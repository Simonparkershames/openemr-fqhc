<?php

/**
 * Maps the logged-in user to a workspace role (issue #33).
 *
 * Resolution order: an explicit per-user override (the value of the
 * fqhc_workspace_override user-editable global) wins; otherwise the user's
 * certified ACL group memberships are matched against the default OpenEMR
 * group titles. Unknown or unmapped users resolve to null so callers fall
 * back to today's behavior — no new authorization is introduced here.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Workspace;

final class WorkspaceResolver
{
    /**
     * Default OpenEMR gacl group titles → workspace role, in precedence
     * order: when a user belongs to several groups, the role closest to
     * hands-on patient work wins (a physician who is also an administrator
     * lands on the provider workspace, not the manager one).
     */
    private const ACL_GROUP_ROLES = [
        'Physicians' => WorkspaceRole::Provider,
        'Clinicians' => WorkspaceRole::ClinicalSupport,
        'Front Office' => WorkspaceRole::FrontDesk,
        'Administrators' => WorkspaceRole::Manager,
    ];

    /**
     * @param ?string $override raw per-user override setting (role key)
     * @param list<string> $aclGroupTitles the user's gacl group titles
     */
    public function resolve(?string $override, array $aclGroupTitles): ?WorkspaceRole
    {
        $overrideRole = WorkspaceRole::tryFrom(strtolower(trim($override ?? '')));
        if ($overrideRole !== null) {
            return $overrideRole;
        }

        foreach (self::ACL_GROUP_ROLES as $groupTitle => $role) {
            if (in_array($groupTitle, $aclGroupTitles, true)) {
                return $role;
            }
        }

        return null;
    }
}
