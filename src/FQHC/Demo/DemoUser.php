<?php

/**
 * A demo staff account to seed for the FQHC out-of-the-box experience.
 *
 * Each role in a Federally Qualified Health Center logs into its own workspace,
 * so the demo needs one obviously-named account per role, placed in the ONC
 * ACL groups the certified authorization already ships. This is a pure value
 * object; the seeder turns it into a `users` row plus ACL group membership.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Demo;

final readonly class DemoUser
{
    /**
     * @param non-empty-list<string> $aclGroups ACL (gacl ARO) group titles this
     *        user joins, e.g. "Physicians", "Front Office".
     */
    public function __construct(
        public string $username,
        public string $firstName,
        public string $lastName,
        public array $aclGroups,
        public bool $isProvider = false,
    ) {
    }
}
