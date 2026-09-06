<?php

/**
 * Reads tokens.css and returns the tokens it declares, grouped by section.
 *
 * The style guide is generated from the stylesheet rather than from a
 * hand-maintained list, so a token added to tokens.css shows up on the page
 * without anyone remembering to register it — and a token removed there
 * disappears from the guide instead of documenting something that no longer
 * exists.
 *
 * Deliberately a small, exact parser rather than a general CSS parser: it
 * reads the first `:root { ... }` block, treats `/* ----- Label ----- *\/`
 * comments as section headings, and reads one custom property per line. That
 * is the shape tokens.css is written in, and anything outside it is skipped
 * rather than guessed at.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\DesignSystem;

final readonly class TokenSheetParser
{
    /** Section heading: a comment whose text is fenced by runs of dashes. */
    private const SECTION_PATTERN = '/^\s*\/\*\s*-{2,}\s*(?<label>.+?)\s*-{2,}\s*\*\/\s*$/';

    /** One custom-property declaration, with an optional trailing comment. */
    private const DECLARATION_PATTERN =
        '/^\s*(?<name>--[A-Za-z0-9_-]+)\s*:\s*(?<value>[^;]+);\s*(?:\/\*\s*(?<comment>.*?)\s*\*\/)?\s*$/';

    /** Label for tokens declared before the first section heading. */
    private const DEFAULT_GROUP_LABEL = 'General';

    /**
     * @throws \RuntimeException when the file cannot be read.
     */
    public function parseFile(string $path): TokenSheet
    {
        $css = @file_get_contents($path);
        if ($css === false) {
            throw new \RuntimeException('Unable to read design token stylesheet.');
        }

        return $this->parse($css);
    }

    public function parse(string $css): TokenSheet
    {
        $block = $this->rootBlock($css);
        if ($block === null) {
            return new TokenSheet([]);
        }

        $groups = [];
        $label = self::DEFAULT_GROUP_LABEL;
        /** @var list<DesignToken> $pending */
        $pending = [];

        foreach (explode("\n", $block) as $line) {
            if (preg_match(self::SECTION_PATTERN, $line, $section) === 1) {
                if ($pending !== []) {
                    $groups[] = new TokenGroup($label, $pending);
                    $pending = [];
                }
                $label = $section['label'];
                continue;
            }

            if (preg_match(self::DECLARATION_PATTERN, $line, $declaration) !== 1) {
                continue;
            }

            $name = $declaration['name'];
            $value = rtrim($declaration['value']);
            $pending[] = new DesignToken(
                $name,
                $value,
                TokenKind::infer($name, $value),
                $declaration['comment'] ?? '',
            );
        }

        if ($pending !== []) {
            $groups[] = new TokenGroup($label, $pending);
        }

        return new TokenSheet($groups);
    }

    /**
     * The body of the first `:root { ... }` rule, brace-matched so a nested
     * block (none today, but a function value could introduce one) does not
     * truncate the rule early. Null when the stylesheet declares no `:root`.
     */
    private function rootBlock(string $css): ?string
    {
        $selector = strpos($css, ':root');
        if ($selector === false) {
            return null;
        }

        $open = strpos($css, '{', $selector);
        if ($open === false) {
            return null;
        }

        $depth = 0;
        $length = strlen($css);
        for ($i = $open; $i < $length; $i++) {
            if ($css[$i] === '{') {
                $depth++;
                continue;
            }
            if ($css[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($css, $open + 1, $i - $open - 1);
                }
            }
        }

        return null;
    }
}
