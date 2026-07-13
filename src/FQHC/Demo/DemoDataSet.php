<?php

/**
 * The deterministic definition of the FQHC demo clinic.
 *
 * This is the whole "living clinic" as pure data: the role staff accounts and a
 * fixed panel of fictional patients built so that every surface an evaluator
 * sees on first login is populated — the UDS patient-characteristics and
 * utilization tables (a spread across every race roll-up line, both ethnicity
 * columns, every FPL income band, a Medicaid-heavy payer mix, each special
 * population), the eligibility/data-quality worklist (a deliberate minority with
 * missing income, race, or coverage), and today's schedule (patients at each
 * check-in state across two providers).
 *
 * Everything here is deterministic and free of the database, the clock, and
 * randomness, so the panel can be asserted wholesale in an isolated test and a
 * re-run seeds byte-for-byte the same clinic. The one thing resolved at seed
 * time is the calendar: the reporting-year visits are dated to "last completed
 * year" and the schedule to "today", which the seeder supplies.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Demo;

use OpenEMR\FQHC\Fpl\IncomeDetermination;
use OpenEMR\FQHC\SpecialPopulation\SpecialPopulation;
use OpenEMR\FQHC\SpecialPopulation\SpecialPopulationStatus;

final class DemoDataSet
{
    /** `pubpid` prefix marking every demo patient for identification and purge. */
    public const PATIENT_ID_PREFIX = 'FQHC-DEMO-';

    /** Shared fictional street so demo patients are obvious in any address view. */
    public const DEMO_STREET = '100 Demonstration Way';
    public const DEMO_CITY = 'Riverton';
    public const DEMO_STATE = 'OR';

    /** `insurance_type_codes` codes for the principal-payer mix (see repository). */
    private const PAYER_MEDICAID = 3;
    private const PAYER_MEDICARE = 2;
    private const PAYER_PRIVATE = 17;
    private const PAYER_BCBS = 6;
    private const PAYER_SELF_PAY = 8;

    /** `openemr_postcalendar_categories.pc_catid` for the seeded visit types. */
    private const CAT_OFFICE_VISIT = 5;
    private const CAT_HBA = 12;
    private const CAT_PREVENTIVE = 13;
    private const CAT_OPHTHALMOLOGICAL = 14;
    private const CAT_GROUP_THERAPY = 15;

    private const PROVIDER_A = 'provider';
    private const PROVIDER_B = 'provider2';

    /**
     * The role accounts. Group titles are the certified gacl ARO groups OpenEMR
     * ships, so no new authorization is introduced.
     *
     * @return non-empty-list<DemoUser>
     */
    public function users(): array
    {
        return [
            new DemoUser('frontdesk', 'Fran', 'Desmond', ['Front Office']),
            new DemoUser('eligibility', 'Elena', 'Vargas', ['Front Office']),
            new DemoUser('ma', 'Maya', 'Ansari', ['Clinicians']),
            new DemoUser(self::PROVIDER_A, 'Priya', 'Rivera', ['Physicians'], isProvider: true),
            new DemoUser(self::PROVIDER_B, 'David', 'Okafor', ['Physicians'], isProvider: true),
            new DemoUser('billing', 'Bianca', 'Lowe', ['Accounting']),
            new DemoUser('manager', 'Marcus', 'Quinn', ['Administrators']),
        ];
    }

    /**
     * The fixed patient panel.
     *
     * @return list<DemoPatient>
     */
    public function patients(): array
    {
        $patients = [];
        $seq = 1;
        foreach ($this->patientSpecs() as $spec) {
            $patients[] = $this->buildPatient($seq, $spec);
            $seq++;
        }

        return $patients;
    }

    /**
     * The panel as plain rows. Each row explicitly names its buckets so the
     * coverage is auditable at a glance and the isolated test can prove every
     * UDS line, payer category, income band, and special population is present,
     * plus a deliberate minority of data-quality gaps.
     *
     * Columns: firstName, lastName, sex, dob, race, ethnicity, language,
     * interpreter, zip, incomeBand ('le100'|'101-150'|'151-200'|'gt200'|
     * 'unknown'|'none'), payer (code or null), specialPops (list), visitCat,
     * virtual, appt (null | [providerUsername, time, status, reason]).
     *
     * @return list<array{
     *     string, string, string, string, string, string, string, bool, string,
     *     string, ?int, list<SpecialPopulationStatus>, int, bool,
     *     ?array{string, string, DemoAppointmentStatus, string}
     * }>
     */
    private function patientSpecs(): array
    {
        $S = fn (SpecialPopulation $p, ?string $sub = null): SpecialPopulationStatus
            => new SpecialPopulationStatus($p, $sub);

        return [
            // --- Today's schedule: patients at each check-in state, two providers ---
            ['Rosa', 'Mendez', 'Female', '1988-03-14', 'white', 'hisp_or_latin', 'spanish', true, '97201',
                'le100', self::PAYER_MEDICAID, [], self::CAT_OFFICE_VISIT, false,
                [self::PROVIDER_A, '08:00', DemoAppointmentStatus::Roomed, 'Diabetes follow-up']],
            ['James', 'Whitfield', 'Male', '1971-11-02', 'black_or_afri_amer', 'not_hisp_or_latin', 'english', false, '97202',
                '101-150', self::PAYER_MEDICAID, [$S(SpecialPopulation::Veteran)], self::CAT_OFFICE_VISIT, false,
                [self::PROVIDER_A, '08:20', DemoAppointmentStatus::Arrived, 'Hypertension check']],
            ['Mei', 'Chen', 'Female', '1995-06-21', 'chinese', 'not_hisp_or_latin', 'chinese', true, '97203',
                '151-200', self::PAYER_PRIVATE, [], self::CAT_OFFICE_VISIT, false,
                [self::PROVIDER_A, '08:40', DemoAppointmentStatus::Scheduled, 'Annual physical']],
            ['Diego', 'Salcedo', 'Male', '2016-09-30', 'white', 'hisp_or_latin', 'spanish', true, '97201',
                'le100', self::PAYER_MEDICAID, [$S(SpecialPopulation::SchoolBased)], self::CAT_PREVENTIVE, false,
                [self::PROVIDER_B, '08:00', DemoAppointmentStatus::Roomed, 'Well-child visit']],
            ['Aaliyah', 'Bryant', 'Female', '2013-01-18', 'black_or_afri_amer', 'not_hisp_or_latin', 'english', false, '97204',
                'le100', self::PAYER_MEDICAID, [], self::CAT_PREVENTIVE, false,
                [self::PROVIDER_B, '08:20', DemoAppointmentStatus::Arrived, 'Asthma follow-up']],
            ['Robert', 'Kowalski', 'Male', '1951-04-09', 'white', 'not_hisp_or_latin', 'english', false, '97205',
                'gt200', self::PAYER_MEDICARE, [], self::CAT_OFFICE_VISIT, false,
                [self::PROVIDER_B, '08:40', DemoAppointmentStatus::Scheduled, 'Medication review']],
            ['Grace', 'Nguyen', 'Female', '1979-07-25', 'vietnamese', 'not_hisp_or_latin', 'vietnamese', true, '97203',
                '101-150', self::PAYER_PRIVATE, [], self::CAT_OFFICE_VISIT, false,
                [self::PROVIDER_A, '09:00', DemoAppointmentStatus::Arrived, 'Thyroid follow-up']],
            ['Marcus', 'Delgado', 'Male', '1983-12-11', 'white', 'hisp_or_latin', 'spanish', false, '97202',
                '151-200', self::PAYER_SELF_PAY, [$S(SpecialPopulation::AgriculturalWorker, 'seasonal')],
                self::CAT_OFFICE_VISIT, false,
                [self::PROVIDER_A, '09:20', DemoAppointmentStatus::Roomed, 'Back pain']],
            ['Linda', 'Osei', 'Female', '1966-02-28', 'black_or_afri_amer', 'not_hisp_or_latin', 'english', false, '97204',
                'gt200', self::PAYER_BCBS, [], self::CAT_OFFICE_VISIT, false,
                [self::PROVIDER_B, '09:00', DemoAppointmentStatus::Scheduled, 'Wellness visit']],
            ['Samuel', 'Redcloud', 'Male', '1990-05-06', 'amer_ind_or_alaska_native', 'not_hisp_or_latin', 'english', false, '97205',
                'unknown', self::PAYER_MEDICAID, [$S(SpecialPopulation::Homeless, 'shelter')],
                self::CAT_OFFICE_VISIT, false,
                [self::PROVIDER_B, '09:20', DemoAppointmentStatus::Arrived, 'Foot infection']],
            ['Isabel', 'Contreras', 'Female', '2001-10-19', 'white', 'hisp_or_latin', 'spanish', true, '97201',
                '101-150', self::PAYER_MEDICAID, [], self::CAT_OFFICE_VISIT, false,
                [self::PROVIDER_A, '09:40', DemoAppointmentStatus::Scheduled, 'Prenatal visit']],
            ['Thomas', 'Fitzgerald', 'Male', '1948-08-13', 'white', 'not_hisp_or_latin', 'english', false, '97202',
                'gt200', self::PAYER_MEDICARE, [], self::CAT_OPHTHALMOLOGICAL, false,
                [self::PROVIDER_B, '09:40', DemoAppointmentStatus::Scheduled, 'Vision exam']],
            ['Fatima', 'Al-Amin', 'Female', '1992-04-02', 'Asian', '', 'english', false, '97203',
                '151-200', self::PAYER_PRIVATE, [], self::CAT_HBA, false,
                [self::PROVIDER_A, '10:00', DemoAppointmentStatus::Arrived, 'Behavioral health intake']],
            ['Kai', 'Palakiko', 'Male', '1985-11-27', 'native_hawai_or_pac_island', 'not_hisp_or_latin', 'english', false, '97204',
                'le100', self::PAYER_MEDICAID, [], self::CAT_OFFICE_VISIT, false,
                [self::PROVIDER_B, '10:00', DemoAppointmentStatus::Scheduled, 'Diabetes education']],

            // --- Reporting-year panel: rounds out UDS coverage, no appt today ---
            ['Carla', 'Jimenez', 'Female', '1974-06-30', 'white', 'hisp_or_latin', 'spanish', true, '97201',
                'le100', self::PAYER_MEDICAID, [$S(SpecialPopulation::AgriculturalWorker, 'migratory')],
                self::CAT_OFFICE_VISIT, false, null],
            ['Andre', 'Booker', 'Male', '1959-03-22', 'black_or_afri_amer', 'not_hisp_or_latin', 'english', false, '97202',
                'gt200', self::PAYER_MEDICARE, [$S(SpecialPopulation::Veteran)], self::CAT_OFFICE_VISIT, false, null],
            ['Sofia', 'Ramirez', 'Female', '1998-09-15', 'white', 'hisp_or_latin', 'spanish', true, '97203',
                '101-150', self::PAYER_MEDICAID, [], self::CAT_OFFICE_VISIT, true, null],
            ['William', 'Tran', 'Male', '2015-12-04', 'vietnamese', 'not_hisp_or_latin', 'vietnamese', false, '97204',
                'le100', self::PAYER_MEDICAID, [$S(SpecialPopulation::SchoolBased)], self::CAT_PREVENTIVE, false, null],
            ['Denise', 'Harmon', 'Female', '1981-01-09', 'white', 'not_hisp_or_latin', 'english', false, '97205',
                '151-200', self::PAYER_PRIVATE, [$S(SpecialPopulation::PublicHousing)], self::CAT_OFFICE_VISIT, false, null],
            ['Miguel', 'Santos', 'Male', '1963-07-17', 'filipino', 'not_hisp_or_latin', 'english', false, '97201',
                '101-150', self::PAYER_MEDICARE, [], self::CAT_OFFICE_VISIT, false, null],
            ['Hannah', 'Goldberg', 'Female', '1977-05-23', 'white', 'not_hisp_or_latin', 'english', false, '97202',
                'gt200', self::PAYER_BCBS, [], self::CAT_GROUP_THERAPY, false, null],
            ['Terrell', 'Freeman', 'Male', '2009-02-11', 'black_or_afri_amer', 'not_hisp_or_latin', 'english', false, '97203',
                'le100', self::PAYER_MEDICAID, [$S(SpecialPopulation::SchoolBased)], self::CAT_PREVENTIVE, false, null],
            ['Yolanda', 'Cruz', 'Female', '1955-10-08', 'white', 'hisp_or_latin', 'spanish', true, '97204',
                'unknown', self::PAYER_MEDICARE, [], self::CAT_OFFICE_VISIT, false, null],
            ['Peter', 'Ahmadi', 'Male', '1994-08-29', 'Asian', 'not_hisp_or_latin', 'english', false, '97205',
                '151-200', self::PAYER_PRIVATE, [], self::CAT_OFFICE_VISIT, true, null],
            ['Nadia', 'Ivanova', 'Female', '1986-12-16', 'white', 'not_hisp_or_latin', 'english', false, '97201',
                '101-150', self::PAYER_SELF_PAY, [$S(SpecialPopulation::Homeless, 'transitional')],
                self::CAT_OFFICE_VISIT, false, null],
            ['Jerome', 'Watkins', 'Male', '1972-04-05', 'black_or_afri_amer', 'not_hisp_or_latin', 'english', false, '97202',
                'le100', self::PAYER_MEDICAID, [$S(SpecialPopulation::Veteran)], self::CAT_HBA, false, null],
            ['Camila', 'Flores', 'Female', '2018-06-12', 'white', 'hisp_or_latin', 'spanish', true, '97203',
                'le100', self::PAYER_MEDICAID, [], self::CAT_PREVENTIVE, false, null],
            ['George', 'Papadopoulos', 'Male', '1949-09-27', 'white', 'not_hisp_or_latin', 'english', false, '97204',
                'gt200', self::PAYER_MEDICARE, [], self::CAT_OPHTHALMOLOGICAL, false, null],
            ['Aisha', 'Bello', 'Female', '1990-03-08', 'black_or_afri_amer', 'not_hisp_or_latin', 'english', false, '97205',
                '151-200', self::PAYER_PRIVATE, [], self::CAT_OFFICE_VISIT, false, null],
            ['Hector', 'Nunez', 'Male', '1968-11-19', 'white', 'hisp_or_latin', 'spanish', false, '97201',
                '101-150', self::PAYER_MEDICAID, [$S(SpecialPopulation::AgriculturalWorker, 'seasonal')],
                self::CAT_OFFICE_VISIT, false, null],

            // --- Deliberate data-quality gaps for the eligibility worklist ---
            // Unreported race:
            ['Kevin', 'Doe', 'Male', '1980-07-14', '', 'not_hisp_or_latin', 'english', false, '97202',
                '151-200', self::PAYER_PRIVATE, [], self::CAT_OFFICE_VISIT, false, null],
            // Unreported ethnicity:
            ['Brianna', 'Cole', 'Female', '1993-05-30', 'white', '', 'english', false, '97203',
                '101-150', self::PAYER_MEDICAID, [], self::CAT_OFFICE_VISIT, false, null],
            // No income on file:
            ['Omar', 'Haddad', 'Male', '1975-02-21', 'Asian', 'not_hisp_or_latin', 'english', false, '97204',
                'none', self::PAYER_MEDICAID, [], self::CAT_OFFICE_VISIT, false, null],
            // Uninsured (no coverage row) and income unknown:
            ['Latoya', 'Simmons', 'Female', '1987-10-03', 'black_or_afri_amer', 'not_hisp_or_latin', 'english', false, '97205',
                'unknown', null, [$S(SpecialPopulation::Homeless, 'street')], self::CAT_OFFICE_VISIT, false, null],
            // Uninsured child:
            ['Noah', 'Barnes', 'Male', '2012-08-08', 'white', 'not_hisp_or_latin', 'english', false, '97201',
                'le100', null, [], self::CAT_PREVENTIVE, false, null],

            // --- Depth: more everyday visits so utilization looks like a real year ---
            ['Priscilla', 'Vega', 'Female', '1996-01-27', 'white', 'hisp_or_latin', 'spanish', true, '97202',
                'le100', self::PAYER_MEDICAID, [], self::CAT_OFFICE_VISIT, false, null],
            ['Daniel', 'Kim', 'Male', '1984-06-15', 'korean', 'not_hisp_or_latin', 'english', false, '97203',
                'gt200', self::PAYER_PRIVATE, [], self::CAT_OFFICE_VISIT, false, null],
            ['Renee', 'Jackson', 'Female', '1961-09-04', 'black_or_afri_amer', 'not_hisp_or_latin', 'english', false, '97204',
                '151-200', self::PAYER_MEDICARE, [], self::CAT_OFFICE_VISIT, false, null],
            ['Anthony', 'Russo', 'Male', '1978-03-19', 'white', 'not_hisp_or_latin', 'english', false, '97205',
                '101-150', self::PAYER_BCBS, [], self::CAT_OFFICE_VISIT, false, null],
            ['Valentina', 'Moreno', 'Female', '2004-11-23', 'white', 'hisp_or_latin', 'spanish', false, '97201',
                'le100', self::PAYER_MEDICAID, [], self::CAT_OFFICE_VISIT, false, null],
            ['Christopher', 'Bell', 'Male', '1953-05-11', 'white', 'not_hisp_or_latin', 'english', false, '97202',
                'gt200', self::PAYER_MEDICARE, [$S(SpecialPopulation::Veteran)], self::CAT_OFFICE_VISIT, false, null],
            ['Destiny', 'Coleman', 'Female', '1999-07-07', 'black_or_afri_amer', 'not_hisp_or_latin', 'english', false, '97203',
                'le100', self::PAYER_MEDICAID, [$S(SpecialPopulation::PublicHousing)], self::CAT_OFFICE_VISIT, false, null],
            ['Ali', 'Rahimi', 'Male', '1991-12-30', 'Asian', 'not_hisp_or_latin', 'english', true, '97204',
                '151-200', self::PAYER_PRIVATE, [], self::CAT_OFFICE_VISIT, false, null],
            ['Monica', 'Estrada', 'Female', '1970-02-14', 'white', 'hisp_or_latin', 'spanish', true, '97205',
                '101-150', self::PAYER_MEDICAID, [$S(SpecialPopulation::AgriculturalWorker, 'migratory')],
                self::CAT_OFFICE_VISIT, false, null],
            ['Gregory', 'Hunt', 'Male', '1965-08-26', 'white', 'not_hisp_or_latin', 'english', false, '97201',
                'gt200', self::PAYER_BCBS, [], self::CAT_GROUP_THERAPY, false, null],
            ['Tanya', 'Woods', 'Female', '1982-04-17', 'black_or_afri_amer', 'not_hisp_or_latin', 'english', false, '97202',
                'unknown', self::PAYER_MEDICAID, [], self::CAT_OFFICE_VISIT, false, null],
            ['Vincent', 'Long', 'Male', '2007-10-01', 'Asian', 'not_hisp_or_latin', 'english', false, '97203',
                'le100', self::PAYER_MEDICAID, [$S(SpecialPopulation::SchoolBased)], self::CAT_PREVENTIVE, false, null],
            ['Sandra', 'Griffin', 'Female', '1957-06-09', 'white', 'not_hisp_or_latin', 'english', false, '97204',
                'gt200', self::PAYER_MEDICARE, [], self::CAT_OPHTHALMOLOGICAL, false, null],
            ['Ricardo', 'Fuentes', 'Male', '1989-01-05', 'white', 'hisp_or_latin', 'spanish', false, '97205',
                '151-200', self::PAYER_SELF_PAY, [], self::CAT_OFFICE_VISIT, false, null],
            ['Erica', 'Nash', 'Female', '1973-11-13', 'black_or_afri_amer', 'not_hisp_or_latin', 'english', false, '97201',
                '101-150', self::PAYER_MEDICAID, [], self::CAT_HBA, false, null],
            ['Patrick', 'O\'Brien', 'Male', '1946-03-28', 'white', 'not_hisp_or_latin', 'english', false, '97202',
                'gt200', self::PAYER_MEDICARE, [], self::CAT_OFFICE_VISIT, false, null],
        ];
    }

    /**
     * @param array{
     *     string, string, string, string, string, string, string, bool, string,
     *     string, ?int, list<SpecialPopulationStatus>, int, bool,
     *     ?array{string, string, DemoAppointmentStatus, string}
     * } $spec
     */
    private function buildPatient(int $seq, array $spec): DemoPatient
    {
        [
            $firstName, $lastName, $sex, $dob, $race, $ethnicity, $language,
            $interpreter, $zip, $incomeBand, $payer, $specialPops, $visitCat,
            $virtual, $appt,
        ] = $spec;

        $appointment = $appt === null
            ? null
            : new DemoAppointment($appt[0], $appt[1], 20, $appt[2], $appt[3]);

        return new DemoPatient(
            externalId: sprintf('%s%03d', self::PATIENT_ID_PREFIX, $seq),
            firstName: $firstName,
            lastName: $lastName,
            dateOfBirth: $dob,
            sex: $sex,
            race: $race,
            ethnicity: $ethnicity,
            language: $language,
            interpreterNeeded: $interpreter,
            postalCode: $zip,
            income: $this->incomeForBand($incomeBand),
            specialPopulations: $specialPops,
            payerTypeCode: $payer,
            priorYearVisitCategoryId: $visitCat,
            priorYearVisitVirtual: $virtual,
            appointment: $appointment,
        );
    }

    /**
     * A household size + annual income that lands squarely in the requested FPL
     * band under the 2025 contiguous guideline (base $15,650, +$5,500/person),
     * so the seeded Table 4 income lines are unambiguous. 'unknown' records an
     * explicit declined determination; 'none' leaves no income row at all.
     */
    private function incomeForBand(string $band): ?IncomeDetermination
    {
        return match ($band) {
            // size 3 → 100% threshold $26,650.
            'le100' => new IncomeDetermination(3, 18000.0),
            '101-150' => new IncomeDetermination(3, 34000.0),
            '151-200' => new IncomeDetermination(3, 46000.0),
            'gt200' => new IncomeDetermination(3, 72000.0),
            'unknown' => new IncomeDetermination(null, null, true),
            'none' => null,
            default => null,
        };
    }
}
