<?php

/**
 * Tests the workspace registry (issue #33): every role has a complete home
 * definition, card URLs are webroot-relative, the manager/quality workspace
 * carries the module's original home surfaces, and the default workspace is
 * the manager one. Runs in isolation (no DB/Docker).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\FQHC\Workspace;

use OpenEMR\FQHC\Workspace\WorkspaceCard;
use OpenEMR\FQHC\Workspace\WorkspaceRegistry;
use OpenEMR\FQHC\Workspace\WorkspaceRole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WorkspaceRegistryTest extends TestCase
{
    private const MODULE_PUBLIC_PATH = '/interface/modules/custom_modules/oe-module-fqhc/public';

    private WorkspaceRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new WorkspaceRegistry();
    }

    /**
     * @return array<string, array{WorkspaceRole}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function roleProvider(): array
    {
        return [
            'front desk' => [WorkspaceRole::FrontDesk],
            'clinical support' => [WorkspaceRole::ClinicalSupport],
            'provider' => [WorkspaceRole::Provider],
            'manager' => [WorkspaceRole::Manager],
        ];
    }

    #[DataProvider('roleProvider')]
    public function testEveryRoleHasACompleteWorkspaceHome(WorkspaceRole $role): void
    {
        $workspace = $this->registry->forRole($role);

        self::assertSame($role, $workspace->role);
        self::assertNotSame('', $workspace->heading);
        self::assertNotSame('', $workspace->tagline);
        self::assertGreaterThanOrEqual(
            2,
            count($workspace->cards),
            'A workspace home needs a meaningful card set.',
        );

        foreach ($workspace->cards as $card) {
            self::assertNotSame('', $card->title);
            self::assertNotSame('', $card->description);
            self::assertNotSame('', $card->ctaLabel);
            self::assertStringStartsWith(
                '/',
                $card->url,
                'Card URLs must be webroot-relative so entry points can prefix the webroot.',
            );
        }
    }

    #[DataProvider('roleProvider')]
    public function testWorkspaceHomesDifferPerRole(WorkspaceRole $role): void
    {
        $workspace = $this->registry->forRole($role);

        foreach (WorkspaceRole::cases() as $otherRole) {
            if ($otherRole === $role) {
                continue;
            }
            self::assertNotSame(
                $this->registry->forRole($otherRole)->heading,
                $workspace->heading,
                'Each role must land on a distinct, role-appropriate workspace home.',
            );
        }
    }

    public function testManagerWorkspaceCarriesTheOriginalHomeSurfaces(): void
    {
        $urls = array_map(
            static fn(WorkspaceCard $card): string => $card->url,
            $this->registry->forRole(WorkspaceRole::Manager)->cards,
        );

        self::assertContains(self::MODULE_PUBLIC_PATH . '/report.php', $urls);
        self::assertContains(self::MODULE_PUBLIC_PATH . '/eligibility-worklist.php', $urls);
        self::assertContains(self::MODULE_PUBLIC_PATH . '/index.php', $urls);
    }

    public function testDefaultWorkspaceIsTheManagerQualityHome(): void
    {
        self::assertSame(WorkspaceRole::Manager, $this->registry->defaultWorkspace()->role);
    }

    public function testAllReturnsOneWorkspacePerRole(): void
    {
        $roles = array_map(
            static fn($workspace): WorkspaceRole => $workspace->role,
            $this->registry->all(),
        );

        self::assertSame(WorkspaceRole::cases(), $roles);
    }

    public function testCardRejectsNonWebrootRelativeUrl(): void
    {
        $this->expectException(\DomainException::class);

        new WorkspaceCard('Title', 'Description', 'https://example.test/page', 'Open');
    }
}
