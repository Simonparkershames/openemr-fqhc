<?php

/**
 * Turns the {@see DemoDataSet} into a living clinic in the database.
 *
 * This is the one place the demo touches OpenEMR's data layer. It writes through
 * the certified service classes (patient, appointment, encounter, insurance) and
 * the FQHC repositories rather than raw SQL, so UUIDs, id sequences, the `forms`
 * row behind each encounter, and the domain events all stay correct. Every step
 * is idempotent — patients are keyed by their `pubpid` marker, income and
 * special-population rows upsert, insurance and reporting-year encounters are
 * created only when absent, and today's schedule is rebuilt each run — so the
 * command is safe to re-run and always lands the same clinic dated to "now".
 *
 * Nothing here decides whether it is safe to run in this environment; that guard
 * lives at the CLI boundary, which must confirm this is a demo/eval install and
 * supply the acting administrator's credentials (user creation is a privileged
 * operation and OpenEMR verifies the admin's own password).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Demo;

use OpenEMR\Common\Acl\AclExtended;
use OpenEMR\Common\Auth\AuthUtils;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Uuid\UuidRegistry;
use OpenEMR\FQHC\Income\PatientIncomeRepository;
use OpenEMR\FQHC\SpecialPopulation\PatientSpecialPopulationRepository;
use OpenEMR\Services\AppointmentService;
use OpenEMR\Services\EncounterService;
use OpenEMR\Services\InsuranceCompanyService;
use OpenEMR\Services\InsuranceService;
use OpenEMR\Services\PatientService;
use OpenEMR\Services\UserService;
use Psr\Clock\ClockInterface;

final readonly class DemoDataSeeder
{
    private const TELEHEALTH_POS_CODE = 11;
    private const SCHEDULE_CATEGORY = 5; // Office Visit

    /**
     * Display names for the demo insurance companies, keyed by
     * `insurance_type_codes` code. One company is created per payer type and
     * reused across patients.
     */
    private const PAYER_COMPANY_NAMES = [
        2 => 'Demo Medicare',
        3 => 'Demo Medicaid',
        6 => 'Demo Blue Cross Blue Shield',
        8 => 'Demo Self-Pay',
        17 => 'Demo Commercial Health Plan',
    ];

    public function __construct(
        private DemoDataSet $dataSet,
        private ClockInterface $clock,
        private PatientService $patientService,
        private AppointmentService $appointmentService,
        private EncounterService $encounterService,
        private InsuranceCompanyService $insuranceCompanyService,
        private InsuranceService $insuranceService,
        private UserService $userService,
        private PatientIncomeRepository $incomeRepository,
        private PatientSpecialPopulationRepository $specialPopulationRepository,
        private AuthUtils $authUtils,
    ) {
    }

    /**
     * Seed the whole demo clinic.
     *
     * @param int    $adminUserId      acting administrator's `users.id`
     * @param string $adminPassword    that administrator's current password
     *                                 (OpenEMR verifies it before creating users)
     * @param string $demoUserPassword password assigned to every demo account
     */
    public function seed(int $adminUserId, string $adminPassword, string $demoUserPassword): SeedResult
    {
        $result = new SeedResult();

        $now = $this->clock->now();
        $reportingYear = (int) $now->format('Y') - 1;
        $scheduleDate = $now->format('Y-m-d');

        $facilityId = $this->resolveDefaultFacilityId();
        $payerCompanyIds = $this->seedInsuranceCompanies($result);
        $this->seedUsers($result, $adminUserId, $adminPassword, $demoUserPassword);

        $providerUsernames = array_values(array_filter(
            array_map(
                static fn (DemoUser $u): ?string => $u->isProvider ? $u->username : null,
                $this->dataSet->users(),
            ),
        ));

        $index = 0;
        foreach ($this->dataSet->patients() as $patient) {
            $pid = $this->upsertPatient($patient, $result);

            $this->seedIncome($pid, $patient);
            $this->seedSpecialPopulations($pid, $patient);
            $this->seedInsurance($pid, $patient, $payerCompanyIds, $reportingYear, $result);

            // Round-robin the reporting-year visit across providers so Table 5
            // and the provider workspaces are not all one clinician.
            $encounterProvider = $providerUsernames === []
                ? null
                : $providerUsernames[$index % count($providerUsernames)];

            $this->seedReportingYearEncounter($pid, $patient, $encounterProvider, $facilityId, $reportingYear, $result);
            $this->seedTodaySchedule($pid, $patient, $facilityId, $scheduleDate, $result);

            $index++;
        }

        return $result;
    }

    private function upsertPatient(DemoPatient $patient, SeedResult $result): int
    {
        $existing = $this->findPatientPid($patient->externalId);
        if ($existing !== null) {
            $result->patientsSkipped++;
            return $existing;
        }

        $data = [
            'fname' => $patient->firstName,
            'lname' => $patient->lastName,
            'DOB' => $patient->dateOfBirth,
            'sex' => $patient->sex,
            'race' => $patient->race,
            'ethnicity' => $patient->ethnicity,
            'language' => $patient->language,
            'interpreter_needed' => $patient->interpreterNeeded ? 'yes' : 'no',
            'street' => DemoDataSet::DEMO_STREET,
            'city' => DemoDataSet::DEMO_CITY,
            'state' => DemoDataSet::DEMO_STATE,
            'postal_code' => $patient->postalCode,
            'pubpid' => $patient->externalId,
        ];

        $processing = $this->patientService->insert($data);
        if (!$processing->isValid() || !$processing->hasData()) {
            $result->addWarning('Failed to create demo patient ' . $patient->externalId);
            throw new DemoSeedException('Could not insert demo patient ' . $patient->externalId);
        }

        $row = $processing->getFirstDataResult();
        if (!is_array($row) || !isset($row['pid']) || !is_numeric($row['pid'])) {
            throw new DemoSeedException('Demo patient insert returned no pid for ' . $patient->externalId);
        }

        $result->patientsCreated++;

        return (int) $row['pid'];
    }

    private function seedIncome(int $pid, DemoPatient $patient): void
    {
        if ($patient->income === null) {
            return; // deliberate data-quality gap
        }

        $this->incomeRepository->save($pid, $patient->income, null);
    }

    private function seedSpecialPopulations(int $pid, DemoPatient $patient): void
    {
        foreach ($patient->specialPopulations as $status) {
            $this->specialPopulationRepository->save($pid, $status, null);
        }
    }

    /**
     * @param array<int, int> $payerCompanyIds payer type code => company id
     */
    private function seedInsurance(
        int $pid,
        DemoPatient $patient,
        array $payerCompanyIds,
        int $reportingYear,
        SeedResult $result,
    ): void {
        if ($patient->payerTypeCode === null) {
            return; // deliberately uninsured
        }

        $companyId = $payerCompanyIds[$patient->payerTypeCode] ?? null;
        if ($companyId === null) {
            $result->addWarning('No demo insurance company for payer code ' . $patient->payerTypeCode);
            return;
        }

        if ($this->hasPrimaryInsurance($pid)) {
            return;
        }

        $this->insuranceService->insert([
            'pid' => $pid,
            'type' => 'primary',
            'provider' => $companyId,
            'plan_name' => self::PAYER_COMPANY_NAMES[$patient->payerTypeCode] ?? 'Demo Plan',
            'policy_number' => 'DEMO' . str_pad((string) $pid, 6, '0', STR_PAD_LEFT),
            'subscriber_relationship' => 'self',
            'subscriber_fname' => $patient->firstName,
            'subscriber_lname' => $patient->lastName,
            'subscriber_DOB' => $patient->dateOfBirth,
            'date' => sprintf('%04d-01-01', $reportingYear),
        ]);

        $result->insuranceRowsCreated++;
    }

    private function seedReportingYearEncounter(
        int $pid,
        DemoPatient $patient,
        ?string $providerUsername,
        int $facilityId,
        int $reportingYear,
        SeedResult $result,
    ): void {
        if ($this->hasEncounterInYear($pid, $reportingYear)) {
            return;
        }

        // A mid-year date keeps the visit unambiguously inside the reporting year.
        $date = sprintf('%04d-06-15', $reportingYear);
        $this->createEncounter(
            $pid,
            $patient->priorYearVisitCategoryId,
            $patient->priorYearVisitVirtual,
            $providerUsername,
            $facilityId,
            $date,
            'Reporting-year visit',
            $result,
        );
    }

    private function seedTodaySchedule(
        int $pid,
        DemoPatient $patient,
        int $facilityId,
        string $scheduleDate,
        SeedResult $result,
    ): void {
        $appointment = $patient->appointment;
        if ($appointment === null) {
            return;
        }

        // Rebuild the demo patient's schedule each run so "today" stays current
        // and re-runs never stack duplicate slots.
        $this->clearDemoAppointments($pid);

        $providerId = $this->userIdByUsername($appointment->providerUsername);
        if ($providerId === null) {
            $result->addWarning(
                'Provider ' . $appointment->providerUsername . ' missing; appointment left unassigned',
            );
        }

        $this->appointmentService->insert($pid, [
            'pc_catid' => self::SCHEDULE_CATEGORY,
            'pc_title' => $appointment->reason,
            'pc_duration' => $appointment->durationMinutes * 60,
            'pc_hometext' => $appointment->reason,
            'pc_apptstatus' => $appointment->status->value,
            'pc_startTime' => $appointment->startTime,
            'pc_eventDate' => $scheduleDate,
            'pc_facility' => $facilityId,
            'pc_billing_location' => $facilityId,
            'pc_aid' => $providerId,
        ]);
        $result->appointmentsCreated++;

        // Arrived/roomed patients have a started encounter for today's visit,
        // giving the provider workspace live "open encounters".
        if ($appointment->status->opensEncounter() && !$this->hasEncounterOnDate($pid, $scheduleDate)) {
            $this->createEncounter(
                $pid,
                self::SCHEDULE_CATEGORY,
                false,
                $appointment->providerUsername,
                $facilityId,
                $scheduleDate,
                $appointment->reason,
                $result,
            );
        }
    }

    private function createEncounter(
        int $pid,
        int $categoryId,
        bool $virtual,
        ?string $providerUsername,
        int $facilityId,
        string $date,
        string $reason,
        SeedResult $result,
    ): void {
        $providerId = $providerUsername === null ? null : $this->userIdByUsername($providerUsername);
        $authGroupRaw = $providerUsername === null ? false : $this->userService->getAuthGroupForUser($providerUsername);
        $authGroup = is_string($authGroupRaw) && $authGroupRaw !== '' ? $authGroupRaw : null;
        $posCode = $virtual ? self::TELEHEALTH_POS_CODE : $this->facilityPosCode($facilityId);

        $patientUuid = $this->patientService->getUuid((string) $pid);
        if ($patientUuid === false) {
            $result->addWarning('No uuid for demo patient pid ' . $pid . '; encounter skipped');
            return;
        }
        $puuid = UuidRegistry::uuidToString($patientUuid);

        $this->encounterService->insertEncounter($puuid, [
            'pc_catid' => $categoryId,
            'class_code' => EncounterService::DEFAULT_CLASS_CODE,
            'provider_id' => $providerId ?? 0,
            'reason' => $reason,
            'facility_id' => $facilityId,
            'billing_facility' => $facilityId,
            'pos_code' => $posCode,
            'user' => $providerUsername ?? 'admin',
            'group' => $authGroup ?? 'Default',
            'date' => $date,
        ]);

        $result->encountersCreated++;
    }

    /**
     * Create one insurance company per payer type in the demo mix (idempotent by
     * name) and return the code => company id map.
     *
     * @return array<int, int>
     */
    private function seedInsuranceCompanies(SeedResult $result): array
    {
        $ids = [];
        foreach (self::PAYER_COMPANY_NAMES as $code => $name) {
            $existing = QueryUtils::fetchSingleValue(
                'SELECT id FROM insurance_companies WHERE name = ? LIMIT 1',
                'id',
                [$name],
            );
            if (is_numeric($existing)) {
                $ids[$code] = (int) $existing;
                continue;
            }

            $newId = $this->insuranceCompanyService->insert([
                'name' => $name,
                'ins_type_code' => $code,
                'attn' => '',
                'cms_id' => '',
                'x12_receiver_id' => '',
                'alt_cms_id' => '',
            ]);
            if (is_numeric($newId)) {
                $ids[$code] = (int) $newId;
            } else {
                $result->addWarning('Could not create demo insurance company ' . $name);
            }
        }

        return $ids;
    }

    private function seedUsers(
        SeedResult $result,
        int $adminUserId,
        string $adminPassword,
        string $demoUserPassword,
    ): void {
        foreach ($this->dataSet->users() as $user) {
            if ($this->userIdByUsername($user->username) !== null) {
                $this->ensureAclGroups($user);
                $result->usersSkipped++;
                continue;
            }

            $currentPwd = $adminPassword;
            $newPwd = $demoUserPassword;
            $created = $this->authUtils->updatePassword(
                $adminUserId,
                0,
                $currentPwd,
                $newPwd,
                true,
                [
                    'password' => 'NoLongerUsed',
                    'username' => $user->username,
                    'fname' => $user->firstName,
                    'lname' => $user->lastName,
                    'authorized' => $user->isProvider ? 1 : 0,
                    'active' => 1,
                    'calendar' => $user->isProvider ? 1 : 0,
                ],
                $user->username,
            );

            if (!$created) {
                $error = $this->authUtils->getErrorMessage();
                $result->addWarning(
                    'Could not create demo user ' . $user->username . ': ' . (is_string($error) ? $error : ''),
                );
                continue;
            }

            $this->assignUserUuid($user->username);
            $this->assignAuthGroup($user);
            $this->ensureAclGroups($user);
            $result->usersCreated++;
        }
    }

    private function assignUserUuid(string $username): void
    {
        // AuthUtils::updatePassword creates the row but leaves uuid unset; the
        // admin UI assigns one right after, so mirror that.
        $uuid = UuidRegistry::getRegistryForTable('users')->createUuid();
        QueryUtils::sqlStatementThrowException(
            'UPDATE `users` SET uuid = ? WHERE username = ? AND (uuid IS NULL OR uuid = \'\')',
            [$uuid, $username],
        );
    }

    private function assignAuthGroup(DemoUser $user): void
    {
        // The billing/auth "groups" row that pairs the user with a group name,
        // mirroring the admin UI's user-create flow.
        QueryUtils::sqlStatementThrowException(
            'INSERT INTO `groups` SET name = ?, user = ?',
            [$user->aclGroups[0], $user->username],
        );
    }

    private function ensureAclGroups(DemoUser $user): void
    {
        foreach ($user->aclGroups as $group) {
            AclExtended::addUserAros($user->username, $group);
        }
    }

    private function findPatientPid(string $externalId): ?int
    {
        $pid = QueryUtils::fetchSingleValue(
            'SELECT pid FROM patient_data WHERE pubpid = ? LIMIT 1',
            'pid',
            [$externalId],
        );

        return is_numeric($pid) ? (int) $pid : null;
    }

    private function userIdByUsername(string $username): ?int
    {
        $id = QueryUtils::fetchSingleValue(
            'SELECT id FROM users WHERE username = ? LIMIT 1',
            'id',
            [$username],
        );

        return is_numeric($id) ? (int) $id : null;
    }

    private function hasPrimaryInsurance(int $pid): bool
    {
        $id = QueryUtils::fetchSingleValue(
            "SELECT id FROM insurance_data WHERE pid = ? AND type = 'primary' LIMIT 1",
            'id',
            [$pid],
        );

        return $id !== null;
    }

    private function hasEncounterInYear(int $pid, int $year): bool
    {
        $id = QueryUtils::fetchSingleValue(
            'SELECT encounter FROM form_encounter WHERE pid = ? AND date >= ? AND date < ? LIMIT 1',
            'encounter',
            [$pid, sprintf('%04d-01-01 00:00:00', $year), sprintf('%04d-01-01 00:00:00', $year + 1)],
        );

        return $id !== null;
    }

    private function hasEncounterOnDate(int $pid, string $date): bool
    {
        $id = QueryUtils::fetchSingleValue(
            'SELECT encounter FROM form_encounter WHERE pid = ? AND DATE(date) = ? LIMIT 1',
            'encounter',
            [$pid, $date],
        );

        return $id !== null;
    }

    private function clearDemoAppointments(int $pid): void
    {
        QueryUtils::sqlStatementThrowException(
            'DELETE FROM openemr_postcalendar_events WHERE pc_pid = ?',
            [$pid],
        );
    }

    private function facilityPosCode(int $facilityId): int
    {
        $pos = QueryUtils::fetchSingleValue(
            'SELECT pos_code FROM facility WHERE id = ? LIMIT 1',
            'pos_code',
            [$facilityId],
        );

        return is_numeric($pos) ? (int) $pos : 11;
    }

    private function resolveDefaultFacilityId(): int
    {
        $id = QueryUtils::fetchSingleValue(
            'SELECT id FROM facility ORDER BY service_location DESC, id ASC LIMIT 1',
            'id',
            [],
        );

        if (!is_numeric($id)) {
            throw new DemoSeedException('No facility exists to attach demo visits to');
        }

        return (int) $id;
    }
}
