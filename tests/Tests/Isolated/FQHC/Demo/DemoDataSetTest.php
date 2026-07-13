<?php

/**
 * Coverage guarantees for the FQHC demo panel (issue #35).
 *
 * The demo only earns its keep if the first login shows a *populated* clinic, so
 * these tests assert the pure dataset actually spans every bucket the UDS report
 * and the role workspaces read — each race roll-up line, both ethnicity columns,
 * every FPL income band, the full payer mix, each special population, and a
 * schedule with a patient at each check-in state across two providers — plus a
 * deliberate minority of data-quality gaps for the eligibility worklist, and
 * that the whole thing is deterministic (a re-run seeds the same clinic). Runs
 * in isolation (no DB/Docker).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\FQHC\Demo;

use OpenEMR\FQHC\Demo\DemoAppointmentStatus;
use OpenEMR\FQHC\Demo\DemoDataSet;
use OpenEMR\FQHC\Fpl\FederalPovertyGuideline;
use OpenEMR\FQHC\Fpl\FplBand;
use OpenEMR\FQHC\Fpl\FplCalculator;
use OpenEMR\FQHC\Fpl\FplRegion;
use OpenEMR\FQHC\Reporting\UdsRaceClassifier;
use OpenEMR\FQHC\SpecialPopulation\SpecialPopulation;
use PHPUnit\Framework\TestCase;

final class DemoDataSetTest extends TestCase
{
    private DemoDataSet $dataSet;

    protected function setUp(): void
    {
        $this->dataSet = new DemoDataSet();
    }

    public function testPanelIsSubstantialAndUniquelyKeyed(): void
    {
        $patients = $this->dataSet->patients();

        self::assertGreaterThanOrEqual(50, count($patients), 'The demo needs a clinic-sized panel.');

        $ids = array_map(static fn ($p): string => $p->externalId, $patients);
        self::assertSame($ids, array_values(array_unique($ids)), 'External ids must be unique.');
        foreach ($ids as $id) {
            self::assertStringStartsWith(DemoDataSet::PATIENT_ID_PREFIX, $id);
        }
    }

    public function testDatasetIsDeterministic(): void
    {
        self::assertEquals(
            (new DemoDataSet())->patients(),
            (new DemoDataSet())->patients(),
            'Two builds must produce an identical panel so a re-run is idempotent.',
        );
    }

    public function testEveryReportedRaceRollupLineIsPresent(): void
    {
        $classifier = new UdsRaceClassifier();
        $lines = [];
        foreach ($this->dataSet->patients() as $patient) {
            $lines[$classifier->classify($patient->race === '' ? null : $patient->race)->rollupLine()] = true;
        }

        // Lines 1 (Asian), 2 (NHOPI), 3 (Black), 4 (AI/AN), 5 (White), 7 (Unreported).
        // Line 6 (more than one race) is not representable from OpenEMR's single race field.
        foreach ([1, 2, 3, 4, 5, 7] as $line) {
            self::assertArrayHasKey($line, $lines, "Race roll-up line {$line} must have at least one demo patient.");
        }
    }

    public function testBothEthnicityColumnsAndUnreportedArePresent(): void
    {
        $ethnicities = array_map(static fn ($p): string => $p->ethnicity, $this->dataSet->patients());

        self::assertContains('hisp_or_latin', $ethnicities);
        self::assertContains('not_hisp_or_latin', $ethnicities);
        self::assertContains('', $ethnicities, 'An unreported ethnicity is needed for the worklist.');
    }

    public function testEveryIncomeBandIsPresent(): void
    {
        $guideline = new FederalPovertyGuideline(2025, FplRegion::Contiguous, 15650.0, 5500.0);
        $calculator = new FplCalculator();

        $bands = [];
        foreach ($this->dataSet->patients() as $patient) {
            if ($patient->income === null) {
                continue;
            }
            $bands[$calculator->calculate($patient->income, $guideline)->band->name] = true;
        }

        foreach ([FplBand::AtOrBelow100, FplBand::From101To150, FplBand::From151To200, FplBand::Above200, FplBand::Unknown] as $band) {
            self::assertArrayHasKey($band->name, $bands, "Income band {$band->name} must be represented.");
        }
    }

    public function testPayerMixCoversEveryCategoryIncludingUninsured(): void
    {
        $codes = array_map(static fn ($p): ?int => $p->payerTypeCode, $this->dataSet->patients());

        self::assertContains(3, $codes, 'Medicaid (heavy for an FQHC) must be present.');
        self::assertContains(2, $codes, 'Medicare must be present.');
        self::assertContains(8, $codes, 'Self-pay must be present.');
        self::assertContains(null, $codes, 'At least one uninsured patient is needed for the worklist.');

        $medicaidShare = count(array_filter($codes, static fn ($c): bool => $c === 3)) / count($codes);
        self::assertGreaterThan(0.25, $medicaidShare, 'Payer mix should be Medicaid-heavy.');
    }

    public function testEverySpecialPopulationIsPresent(): void
    {
        $seen = [];
        foreach ($this->dataSet->patients() as $patient) {
            foreach ($patient->specialPopulations as $status) {
                $seen[$status->population->value] = true;
            }
        }

        foreach (SpecialPopulation::cases() as $population) {
            self::assertArrayHasKey($population->value, $seen, "Special population {$population->value} must be represented.");
        }
    }

    public function testDeliberateDataQualityGapsExist(): void
    {
        $patients = $this->dataSet->patients();

        $missingRace = array_filter($patients, static fn ($p): bool => $p->race === '');
        $missingIncome = array_filter($patients, static fn ($p): bool => $p->income === null);
        $uninsured = array_filter($patients, static fn ($p): bool => $p->payerTypeCode === null);

        self::assertNotEmpty($missingRace, 'Need an unreported-race gap for the worklist.');
        self::assertNotEmpty($missingIncome, 'Need a missing-income gap for the worklist.');
        self::assertNotEmpty($uninsured, 'Need an uninsured gap for the worklist.');

        // ...but the gaps must be a minority, so the report still looks healthy.
        self::assertLessThan(count($patients) * 0.25, count($missingRace) + count($missingIncome) + count($uninsured));
    }

    public function testEveryPatientIsInTheReportingCohort(): void
    {
        foreach ($this->dataSet->patients() as $patient) {
            self::assertGreaterThan(
                0,
                $patient->priorYearVisitCategoryId,
                'Every demo patient needs a reporting-year visit to count in UDS.',
            );
        }
    }

    public function testTodaysScheduleSpansEveryStatusAndTwoProviders(): void
    {
        $statuses = [];
        $providers = [];
        foreach ($this->dataSet->patients() as $patient) {
            if ($patient->appointment === null) {
                continue;
            }
            $statuses[$patient->appointment->status->value] = true;
            $providers[$patient->appointment->providerUsername] = true;
        }

        foreach (DemoAppointmentStatus::cases() as $status) {
            self::assertArrayHasKey($status->value, $statuses, "Schedule must include a {$status->name} patient.");
        }
        self::assertGreaterThanOrEqual(2, count($providers), 'Schedule should span at least two providers.');
    }

    public function testRoleUsersCoverTheCoreRolesWithProviders(): void
    {
        $users = $this->dataSet->users();
        $usernames = array_map(static fn ($u): string => $u->username, $users);

        foreach (['frontdesk', 'ma', 'provider', 'eligibility', 'manager'] as $role) {
            self::assertContains($role, $usernames, "Demo must ship a '{$role}' account.");
        }

        $providers = array_filter($users, static fn ($u): bool => $u->isProvider);
        self::assertGreaterThanOrEqual(2, count($providers), 'Need at least two providers for a multi-provider schedule.');

        // Each role account lands in the ACL group its curated menu expects.
        $groupsByUser = [];
        foreach ($users as $user) {
            $groupsByUser[$user->username] = $user->aclGroups;
        }
        self::assertContains('Physicians', $groupsByUser['provider'] ?? []);
        self::assertContains('Front Office', $groupsByUser['frontdesk'] ?? []);
        self::assertContains('Administrators', $groupsByUser['manager'] ?? []);
    }
}
