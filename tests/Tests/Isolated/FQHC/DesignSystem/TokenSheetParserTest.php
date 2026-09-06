<?php

/**
 * Unit tests for the tokens.css parser behind the living style guide.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\FQHC\DesignSystem;

use OpenEMR\FQHC\DesignSystem\TokenKind;
use OpenEMR\FQHC\DesignSystem\TokenSheetParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TokenSheetParserTest extends TestCase
{
    private const SAMPLE = <<<'CSS'
        /* A file-level comment that is not a section heading. */

        :root {
          --fqhc-untitled: 1;

          /* ----- Color: brand ----- */
          --fqhc-color-primary: #0f766e;          /* teal-700 */
          --fqhc-color-primary-soft: #ccfbf1;

          /* ----- Spacing ----- */
          --fqhc-space-1: 0.25rem;
          --fqhc-space-2: 0.5rem;
        }

        @media (prefers-reduced-motion: reduce) {
          :root {
            --fqhc-transition: 0ms;
          }
        }
        CSS;

    public function testGroupsFollowSectionCommentsInDeclarationOrder(): void
    {
        $sheet = (new TokenSheetParser())->parse(self::SAMPLE);

        self::assertSame(
            ['General', 'Color: brand', 'Spacing'],
            array_map(static fn($group): string => $group->label, $sheet->groups),
        );
    }

    public function testTokensBeforeTheFirstSectionLandInTheDefaultGroup(): void
    {
        $sheet = (new TokenSheetParser())->parse(self::SAMPLE);

        self::assertSame('--fqhc-untitled', $sheet->groups[0]->tokens[0]->name);
    }

    public function testTrailingCommentIsCapturedAndValueExcludesIt(): void
    {
        $sheet = (new TokenSheetParser())->parse(self::SAMPLE);
        $primary = $sheet->groups[1]->tokens[0];

        self::assertSame('--fqhc-color-primary', $primary->name);
        self::assertSame('#0f766e', $primary->value);
        self::assertSame('teal-700', $primary->comment);
    }

    public function testTokenWithoutATrailingCommentHasAnEmptyComment(): void
    {
        $sheet = (new TokenSheetParser())->parse(self::SAMPLE);

        self::assertSame('', $sheet->groups[1]->tokens[1]->comment);
    }

    public function testOnlyTheFirstRootBlockIsRead(): void
    {
        // The reduced-motion override redeclares --fqhc-transition; including it
        // would document a value that only applies under a media query.
        $sheet = (new TokenSheetParser())->parse(self::SAMPLE);

        self::assertArrayNotHasKey('--fqhc-transition', $sheet->values());
        self::assertSame(5, $sheet->count());
    }

    public function testValuesAreKeyedByFullTokenName(): void
    {
        $values = (new TokenSheetParser())->parse(self::SAMPLE)->values();

        self::assertSame('0.5rem', $values['--fqhc-space-2']);
    }

    public function testShortNameDropsTheProjectPrefix(): void
    {
        $sheet = (new TokenSheetParser())->parse(self::SAMPLE);

        self::assertSame('space-1', $sheet->groups[2]->tokens[0]->shortName());
    }

    public function testStylesheetWithoutARootBlockYieldsNoTokens(): void
    {
        $sheet = (new TokenSheetParser())->parse('.card { color: red; }');

        self::assertSame([], $sheet->groups);
        self::assertSame(0, $sheet->count());
    }

    public function testUnreadableFileThrows(): void
    {
        $this->expectException(\RuntimeException::class);

        (new TokenSheetParser())->parseFile(__DIR__ . '/no-such-tokens.css');
    }

    public function testGroupKindIsRawWhenTheGroupMixesKinds(): void
    {
        $sheet = (new TokenSheetParser())->parse(":root {\n  --fqhc-color-a: #fff;\n  --fqhc-space-1: 1rem;\n}");

        self::assertSame(TokenKind::Raw, $sheet->groups[0]->kind());
    }

    public function testGroupKindIsSharedWhenEveryTokenAgrees(): void
    {
        $sheet = (new TokenSheetParser())->parse(":root {\n  --fqhc-space-1: 1rem;\n  --fqhc-space-2: 2rem;\n}");

        self::assertSame(TokenKind::Space, $sheet->groups[0]->kind());
    }

    #[DataProvider('kindProvider')]
    public function testKindIsInferredFromNameThenValue(string $name, string $value, TokenKind $expected): void
    {
        self::assertSame($expected, TokenKind::infer($name, $value));
    }

    /**
     * @return array<string, array{string, string, TokenKind}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function kindProvider(): array
    {
        return [
            'hex colour' => ['--fqhc-color-primary', '#0f766e', TokenKind::Color],
            'short hex colour' => ['--fqhc-color-x', '#fff', TokenKind::Color],
            'rgba colour' => ['--fqhc-surface-veil', 'rgba(0, 0, 0, 0.4)', TokenKind::Color],
            'shadow beats its rgba value' => ['--fqhc-shadow-sm', '0 1px 2px rgba(15, 23, 42, 0.06)', TokenKind::Shadow],
            'focus ring is a shadow' => ['--fqhc-focus-ring', '0 0 0 3px rgba(79, 70, 229, 0.45)', TokenKind::Shadow],
            'font stack' => ['--fqhc-font-sans', '"Inter", system-ui, sans-serif', TokenKind::FontFamily],
            'font size' => ['--fqhc-font-size-lg', '1.125rem', TokenKind::FontSize],
            'font weight' => ['--fqhc-font-weight-medium', '500', TokenKind::FontWeight],
            'spacing step' => ['--fqhc-space-4', '1rem', TokenKind::Space],
            'radius' => ['--fqhc-radius-pill', '999px', TokenKind::Radius],
            'transition' => ['--fqhc-transition', '150ms ease', TokenKind::Motion],
            'anything else' => ['--fqhc-container-max', '1200px', TokenKind::Raw],
        ];
    }
}
