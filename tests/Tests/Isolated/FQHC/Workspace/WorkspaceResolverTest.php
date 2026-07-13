<?php

/**
 * Tests the workspace role resolution rules (issue #33): per-user override
 * wins, then ACL-group mapping in precedence order, and unmapped users
 * resolve to null so callers keep today's behavior. Runs in isolation
 * (no DB/Docker).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\FQHC\Workspace;

use OpenEMR\FQHC\Workspace\WorkspaceResolver;
use OpenEMR\FQHC\Workspace\WorkspaceRole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WorkspaceResolverTest extends TestCase
{
    private WorkspaceResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new WorkspaceResolver();
    }

    /**
     * @return array<string, array{string, list<string>, WorkspaceRole}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function overrideWinsProvider(): array
    {
        return [
            'override beats mapped group' => ['frontdesk', ['Physicians'], WorkspaceRole::FrontDesk],
            'override works with no groups' => ['provider', [], WorkspaceRole::Provider],
            'override is trimmed' => ['  manager  ', ['Front Office'], WorkspaceRole::Manager],
            'override is case-insensitive' => ['Clinical', [], WorkspaceRole::ClinicalSupport],
        ];
    }

    /**
     * @param list<string> $groupTitles
     */
    #[DataProvider('overrideWinsProvider')]
    public function testExplicitOverrideWins(string $override, array $groupTitles, WorkspaceRole $expected): void
    {
        self::assertSame($expected, $this->resolver->resolve($override, $groupTitles));
    }

    /**
     * @return array<string, array{list<string>, WorkspaceRole}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function aclGroupMappingProvider(): array
    {
        return [
            'Physicians map to provider' => [['Physicians'], WorkspaceRole::Provider],
            'Clinicians map to clinical support' => [['Clinicians'], WorkspaceRole::ClinicalSupport],
            'Front Office maps to front desk' => [['Front Office'], WorkspaceRole::FrontDesk],
            'Administrators map to manager' => [['Administrators'], WorkspaceRole::Manager],
            'physician-administrator lands on provider' => [
                ['Administrators', 'Physicians'],
                WorkspaceRole::Provider,
            ],
            'clinician-front-office lands on clinical' => [
                ['Front Office', 'Clinicians'],
                WorkspaceRole::ClinicalSupport,
            ],
            'unmapped group ignored beside mapped one' => [
                ['Emergency Login', 'Front Office'],
                WorkspaceRole::FrontDesk,
            ],
        ];
    }

    /**
     * @param list<string> $groupTitles
     */
    #[DataProvider('aclGroupMappingProvider')]
    public function testAclGroupsMapInPrecedenceOrder(array $groupTitles, WorkspaceRole $expected): void
    {
        self::assertSame($expected, $this->resolver->resolve(null, $groupTitles));
    }

    /**
     * @return array<string, array{?string, list<string>}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function unresolvedProvider(): array
    {
        return [
            'no override, no groups' => [null, []],
            'blank override, no groups' => ['', []],
            'invalid override, no groups' => ['superhero', []],
            'only unmapped groups' => [null, ['Accounting', 'Emergency Login']],
            'group match must be exact' => [null, ['physicians', 'front office']],
            'invalid override falls through to groups being empty' => ['nope', []],
        ];
    }

    /**
     * @param list<string> $groupTitles
     */
    #[DataProvider('unresolvedProvider')]
    public function testUnmappedUsersResolveToNull(?string $override, array $groupTitles): void
    {
        self::assertNull($this->resolver->resolve($override, $groupTitles));
    }

    public function testInvalidOverrideStillFallsBackToGroups(): void
    {
        self::assertSame(
            WorkspaceRole::FrontDesk,
            $this->resolver->resolve('not-a-role', ['Front Office']),
        );
    }
}
