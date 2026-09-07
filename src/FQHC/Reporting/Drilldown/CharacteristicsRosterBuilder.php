<?php

/**
 * Collects the patients behind every UDS patient-characteristics cell.
 *
 * Fed one patient at a time from the report generator's single pass — the same
 * parsed records the count aggregators receive, plus the patient id the
 * aggregators discard. Its cell placement mirrors the aggregators exactly
 * (unclassified insurance folds to None/Uninsured; a patient with several
 * special-population statuses lands in each breakout cell but once in each
 * total), so a cell's roster size always equals that cell's reported count. A
 * patient dropped from an age/sex table (null record) contributes no cell for
 * that table, matching the generator dropping it from the counts.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Reporting\Drilldown;

use OpenEMR\FQHC\Payer\UdsPayerCategory;
use OpenEMR\FQHC\Reporting\Table3aPatientRecord;
use OpenEMR\FQHC\Reporting\Table3bPatientRecord;
use OpenEMR\FQHC\Reporting\Table4PatientRecord;
use OpenEMR\FQHC\Reporting\UdsZipInsuranceColumn;
use OpenEMR\FQHC\Reporting\ZipCodeTablePatientRecord;
use OpenEMR\FQHC\SpecialPopulation\AgriculturalWorkerType;
use OpenEMR\FQHC\SpecialPopulation\HomelessStatus;
use OpenEMR\FQHC\SpecialPopulation\SpecialPopulation;

final class CharacteristicsRosterBuilder
{
    /** @var array<string, list<int>> */
    private array $pidsByCell = [];

    public function add(
        int $pid,
        ?Table3aPatientRecord $table3a,
        Table3bPatientRecord $table3b,
        ?Table4PatientRecord $table4,
        ZipCodeTablePatientRecord $zip,
    ): void {
        if ($table3a !== null) {
            $this->push(CharacteristicsCell::ageSex($table3a->ageBand->line, $table3a->sex), $pid);
        }

        $this->push(
            CharacteristicsCell::raceEthnicity(
                $table3b->race,
                CharacteristicsCell::raceColumnFor($table3b->ethnicity),
            ),
            $pid,
        );
        if ($table3b->bestServedInNonEnglishLanguage) {
            $this->push(CharacteristicsCell::language(), $pid);
        }

        if ($table4 !== null) {
            $this->addTable4($pid, $table4);
        }

        $category = $zip->payerCategory ?? UdsPayerCategory::None;
        $column = UdsZipInsuranceColumn::fromPayerCategory($category);
        $this->push(CharacteristicsCell::zip($zip->residence, $column), $pid);
    }

    public function build(): PatientRoster
    {
        return new PatientRoster($this->pidsByCell);
    }

    private function addTable4(int $pid, Table4PatientRecord $record): void
    {
        $this->push(CharacteristicsCell::income($record->incomeBand), $pid);

        $category = $record->payerCategory ?? UdsPayerCategory::None;
        $this->push(CharacteristicsCell::insurance($category, $record->ageGroup), $pid);

        $populations = [];
        $homelessSubtypes = [];
        $agriculturalSubtypes = [];
        foreach ($record->specialPopulationStatuses as $status) {
            $populations[$status->population->value] = true;
            if ($status->subtype === null) {
                continue;
            }
            if ($status->population === SpecialPopulation::Homeless) {
                $homeless = HomelessStatus::tryFrom($status->subtype);
                if ($homeless !== null) {
                    $homelessSubtypes[$homeless->value] = $homeless;
                }
            }
            if ($status->population === SpecialPopulation::AgriculturalWorker) {
                $agricultural = AgriculturalWorkerType::tryFrom($status->subtype);
                if ($agricultural !== null) {
                    $agriculturalSubtypes[$agricultural->value] = true;
                }
            }
        }

        foreach ($homelessSubtypes as $homeless) {
            $this->push(CharacteristicsCell::homeless($homeless), $pid);
        }
        if (isset($populations[SpecialPopulation::Homeless->value])) {
            $this->push(CharacteristicsCell::homelessTotal(), $pid);
        }

        if (isset($agriculturalSubtypes[AgriculturalWorkerType::Migratory->value])) {
            $this->push(CharacteristicsCell::agriculturalMigratory(), $pid);
        }
        if (isset($agriculturalSubtypes[AgriculturalWorkerType::Seasonal->value])) {
            $this->push(CharacteristicsCell::agriculturalSeasonal(), $pid);
        }
        if (isset($populations[SpecialPopulation::AgriculturalWorker->value])) {
            $this->push(CharacteristicsCell::agriculturalTotal(), $pid);
        }

        if (isset($populations[SpecialPopulation::SchoolBased->value])) {
            $this->push(CharacteristicsCell::schoolBased(), $pid);
        }
        if (isset($populations[SpecialPopulation::Veteran->value])) {
            $this->push(CharacteristicsCell::veterans(), $pid);
        }
        if (isset($populations[SpecialPopulation::PublicHousing->value])) {
            $this->push(CharacteristicsCell::publicHousing(), $pid);
        }
    }

    private function push(string $cell, int $pid): void
    {
        $this->pidsByCell[$cell][] = $pid;
    }
}
