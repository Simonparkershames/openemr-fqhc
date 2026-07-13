<?php

/**
 * The FQHC roles that have a purpose-built workspace home (issue #33).
 *
 * Backed enum because the value is persisted: the per-user workspace override
 * global stores the role key as a string.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Workspace;

enum WorkspaceRole: string
{
    case FrontDesk = 'frontdesk';
    case ClinicalSupport = 'clinical';
    case Provider = 'provider';
    case Manager = 'manager';

    public function label(): string
    {
        return match ($this) {
            self::FrontDesk => 'Front Desk',
            self::ClinicalSupport => 'Clinical Support',
            self::Provider => 'Provider',
            self::Manager => 'Manager & Quality',
        };
    }
}
