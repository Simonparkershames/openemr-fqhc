<?php

/**
 * Tests the CDR action → ScreeningDue parser (issue #37): actionable due
 * states pass through, plans / not-due / unlabeled actions are dropped.
 * Runs in isolation (no DB/Docker).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\FQHC\Rooming;

use OpenEMR\FQHC\Rooming\ScreeningDueFactory;
use OpenEMR\FQHC\Rooming\ScreeningDueStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ScreeningDueFactoryTest extends TestCase
{
    private ScreeningDueFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ScreeningDueFactory();
    }

    /**
     * @return array<string, array{string, ScreeningDueStatus}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function dueStatusProvider(): array
    {
        return [
            'past due' => ['past_due', ScreeningDueStatus::PastDue],
            'due' => ['due', ScreeningDueStatus::Due],
            'soon due' => ['soon_due', ScreeningDueStatus::SoonDue],
        ];
    }

    #[DataProvider('dueStatusProvider')]
    public function testParsesActionableDueStates(string $dueStatus, ScreeningDueStatus $expected): void
    {
        $screening = $this->factory->fromRuleAction(
            ['due_status' => $dueStatus, 'is_plan' => '0'],
            'Preventative Care: Colon Cancer Screening',
        );

        self::assertNotNull($screening);
        self::assertSame('Preventative Care: Colon Cancer Screening', $screening->label);
        self::assertSame($expected, $screening->status);
    }

    public function testDropsNotDueActions(): void
    {
        self::assertNull($this->factory->fromRuleAction(
            ['due_status' => 'not_due', 'is_plan' => '0'],
            'Hypertension: Blood Pressure Measurement',
        ));
    }

    public function testDropsPlanActions(): void
    {
        self::assertNull($this->factory->fromRuleAction(
            ['due_status' => 'due', 'is_plan' => '1'],
            'Diabetes Plan',
        ));
    }

    public function testDropsUnlabeledActions(): void
    {
        self::assertNull($this->factory->fromRuleAction(
            ['due_status' => 'due', 'is_plan' => '0'],
            '   ',
        ));
    }

    public function testDropsActionsWithoutADueStatus(): void
    {
        self::assertNull($this->factory->fromRuleAction(
            ['is_plan' => '0'],
            'Tobacco Use Assessment',
        ));
    }
}
