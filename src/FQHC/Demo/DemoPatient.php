<?php

/**
 * One fictional patient in the FQHC demo panel.
 *
 * Carries every field the UDS report and the role workspaces read: the
 * `patient_data` demographics (as the certified list option_ids the classifiers
 * expect), the income determination and special-population statuses that feed
 * Tables 4, the principal-payer type code for the payer mix, the prior-year
 * visit that puts the patient in the reporting cohort, and an optional slot on
 * today's schedule. Nullable fields are deliberate: a null income or payer is a
 * data gap the eligibility worklist is meant to surface.
 *
 * Pure value object — no database, no services — so the whole panel can be built
 * and asserted in an isolated test. The seeder is what turns it into rows.
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
use OpenEMR\FQHC\SpecialPopulation\SpecialPopulationStatus;

final readonly class DemoPatient
{
    /**
     * @param string                       $externalId   stable idempotency key,
     *        stored as `pubpid` (e.g. "FQHC-DEMO-001") so a re-run updates rather
     *        than duplicates, and staff can identify and purge demo records.
     * @param string                       $race         option_id from the `race`
     *        list, or "" for an intentionally unreported value.
     * @param string                       $ethnicity    option_id from the
     *        `ethnicity` list, or "" for unreported.
     * @param string                       $language     option_id from the
     *        `language` list.
     * @param ?IncomeDetermination         $income       null = no income on file
     *        (a data-quality gap), else the determination feeding the FPL band.
     * @param list<SpecialPopulationStatus> $specialPopulations statuses held
     *        during the reporting year (may be empty).
     * @param ?int                         $payerTypeCode `insurance_type_codes`
     *        code of the principal payer, or null for uninsured (no coverage row).
     * @param int                          $priorYearVisitCategoryId `pc_catid` of
     *        the reporting-year encounter that puts the patient in the cohort.
     * @param bool                         $priorYearVisitVirtual whether that
     *        visit was virtual (place-of-service 11), for the Table 5 virtual line.
     * @param ?DemoAppointment             $appointment  today's slot, or null.
     */
    public function __construct(
        public string $externalId,
        public string $firstName,
        public string $lastName,
        public string $dateOfBirth,
        public string $sex,
        public string $race,
        public string $ethnicity,
        public string $language,
        public bool $interpreterNeeded,
        public string $postalCode,
        public ?IncomeDetermination $income,
        public array $specialPopulations,
        public ?int $payerTypeCode,
        public int $priorYearVisitCategoryId,
        public bool $priorYearVisitVirtual,
        public ?DemoAppointment $appointment,
    ) {
    }
}
