<?php

/**
 * Names of the workspace-framework global settings (issue #33).
 *
 * Kept in the autoloaded domain namespace so both the module bootstrap
 * (which registers the settings) and the workspace entry points (which read
 * them) share one definition.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Workspace;

final class WorkspaceGlobals
{
    /**
     * Bool, default off (upstream-safe): land users on their role workspace
     * as the initial tab after login instead of Calendar/Messages.
     */
    public const LOGIN_LANDING = 'fqhc_workspace_login_landing';

    /**
     * Text, user-editable: per-user workspace role override. Holds a
     * WorkspaceRole value ('frontdesk' | 'clinical' | 'provider' |
     * 'manager'); blank or invalid values fall back to ACL-group mapping.
     */
    public const WORKSPACE_OVERRIDE = 'fqhc_workspace_override';

    private function __construct()
    {
    }
}
