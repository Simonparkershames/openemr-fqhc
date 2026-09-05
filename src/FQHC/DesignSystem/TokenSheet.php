<?php

/**
 * The parsed contents of a design-token stylesheet.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\DesignSystem;

final readonly class TokenSheet
{
    /**
     * @param list<TokenGroup> $groups Groups in declaration order.
     */
    public function __construct(public array $groups)
    {
    }

    /** @return list<DesignToken> */
    public function tokens(): array
    {
        $tokens = [];
        foreach ($this->groups as $group) {
            foreach ($group->tokens as $token) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    /**
     * Token values keyed by full custom-property name.
     *
     * @return array<string, string>
     */
    public function values(): array
    {
        $values = [];
        foreach ($this->tokens() as $token) {
            $values[$token->name] = $token->value;
        }

        return $values;
    }

    public function count(): int
    {
        return count($this->tokens());
    }
}
