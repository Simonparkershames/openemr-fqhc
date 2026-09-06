<?php

/**
 * A titled run of design tokens, delimited in tokens.css by a section comment.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\DesignSystem;

final readonly class TokenGroup
{
    /**
     * @param non-empty-list<DesignToken> $tokens
     */
    public function __construct(
        public string $label,
        public array $tokens,
    ) {
    }

    /**
     * The presentation shared by every token in the group, or TokenKind::Raw
     * when the group mixes kinds. Lets the style guide pick one layout per
     * group instead of switching per row.
     */
    public function kind(): TokenKind
    {
        $kind = $this->tokens[0]->kind;
        foreach ($this->tokens as $token) {
            if ($token->kind !== $kind) {
                return TokenKind::Raw;
            }
        }

        return $kind;
    }
}
