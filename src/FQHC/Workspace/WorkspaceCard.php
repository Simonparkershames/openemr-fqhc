<?php

/**
 * One card on a role workspace home: a titled shortcut into the surface where
 * that role does its work (issue #33).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Workspace;

final readonly class WorkspaceCard
{
    /**
     * @param string $url webroot-relative path (leading slash); the entry
     *                    point prefixes the site's webroot when rendering.
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $url,
        public string $ctaLabel,
    ) {
        if (!str_starts_with($url, '/')) {
            throw new \DomainException('Workspace card URLs must be webroot-relative (leading slash)');
        }
    }
}
