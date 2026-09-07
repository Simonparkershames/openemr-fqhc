<?php

/**
 * One design token: a CSS custom property declared in tokens.css.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\DesignSystem;

final readonly class DesignToken
{
    /**
     * @param string $name    Full custom-property name including the leading `--`.
     * @param string $value   Declared value, verbatim.
     * @param string $comment Trailing `/* ... *\/` note from the declaration, or ''.
     */
    public function __construct(
        public string $name,
        public string $value,
        public TokenKind $kind,
        public string $comment = '',
    ) {
    }

    /** The name without the `--fqhc-` prefix, for compact display. */
    public function shortName(): string
    {
        $short = preg_replace('/^--fqhc-/', '', $this->name);

        return $short ?? $this->name;
    }
}
