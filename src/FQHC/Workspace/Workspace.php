<?php

/**
 * A role workspace home definition: the heading, tagline, and card set that
 * the shared home template renders for one FQHC role (issue #33).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Workspace;

final readonly class Workspace
{
    /**
     * @param non-empty-list<WorkspaceCard> $cards
     */
    public function __construct(
        public WorkspaceRole $role,
        public string $heading,
        public string $tagline,
        public array $cards,
    ) {
    }
}
