<?php

/**
 * Tests the apptstat → front-desk phase classifier (issue #36): every
 * certified seed code maps to the phase front desk expects, and unknown
 * site-added codes stay visible as Expected. Runs in isolation (no
 * DB/Docker).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\FQHC\FrontDesk;

use OpenEMR\FQHC\FrontDesk\AppointmentPhase;
use OpenEMR\FQHC\FrontDesk\AppointmentStatusClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AppointmentStatusClassifierTest extends TestCase
{
    /**
     * @return array<string, array{string, AppointmentPhase}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function statusCodeProvider(): array
    {
        return [
            'none' => ['-', AppointmentPhase::Expected],
            'reminder done' => ['*', AppointmentPhase::Expected],
            'chart pulled' => ['+', AppointmentPhase::Expected],
            'pending' => ['^', AppointmentPhase::Expected],
            'AVM confirmed' => ['AVM', AppointmentPhase::Expected],
            'SMS confirmed' => ['SMS', AppointmentPhase::Expected],
            'email confirmed' => ['EMAIL', AppointmentPhase::Expected],
            'callback requested' => ['CALL', AppointmentPhase::Expected],
            'arrived' => ['@', AppointmentPhase::Arrived],
            'arrived late' => ['~', AppointmentPhase::Arrived],
            'in exam room' => ['<', AppointmentPhase::WithCareTeam],
            'checked out' => ['>', AppointmentPhase::CheckedOut],
            'coding done' => ['$', AppointmentPhase::CheckedOut],
            'insurance/financial issue' => ['#', AppointmentPhase::FinancialIssue],
            'canceled' => ['x', AppointmentPhase::NotComing],
            'canceled under 24h' => ['%', AppointmentPhase::NotComing],
            'no show' => ['?', AppointmentPhase::NotComing],
            'left without visit' => ['!', AppointmentPhase::NotComing],
            'unknown site-added code' => ['ZZ', AppointmentPhase::Expected],
            'empty code' => ['', AppointmentPhase::Expected],
        ];
    }

    #[DataProvider('statusCodeProvider')]
    public function testClassifiesEveryCertifiedCode(string $code, AppointmentPhase $expected): void
    {
        self::assertSame($expected, (new AppointmentStatusClassifier())->classify($code));
    }

    public function testEveryPhaseRendersALabelAndAKnownBadgeVariant(): void
    {
        $allowedVariants = ['success', 'warning', 'danger', 'info', 'neutral'];

        foreach (AppointmentPhase::cases() as $phase) {
            self::assertNotSame('', $phase->label());
            self::assertContains($phase->badgeVariant(), $allowedVariants);
        }
    }

    public function testOnlyInBuildingAndExpectedPhasesAreActive(): void
    {
        $active = array_values(array_filter(
            AppointmentPhase::cases(),
            static fn(AppointmentPhase $phase): bool => $phase->isActive(),
        ));

        self::assertSame(
            [
                AppointmentPhase::Expected,
                AppointmentPhase::Arrived,
                AppointmentPhase::WithCareTeam,
                AppointmentPhase::FinancialIssue,
            ],
            $active,
        );
    }
}
