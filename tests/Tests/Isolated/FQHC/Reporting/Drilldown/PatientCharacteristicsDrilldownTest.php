<?php

/**
 * Isolated tests for the UDS report patient-level drill-down (#42).
 *
 * Drives the whole drill-down chain with an in-memory cohort: the generator
 * builds the roster alongside the counts, and the presenter resolves it against
 * a patient directory. The load-bearing invariant is reconciliation — every
 * drill-down cell lists exactly the patients its reported count claims — so
 * these tests assert the drill-down totals against the report totals, not just
 * spot values.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\FQHC\Reporting\Drilldown;

use OpenEMR\FQHC\Fpl\FplBand;
use OpenEMR\FQHC\Reporting\Drilldown\PatientDirectory;
use OpenEMR\FQHC\Reporting\Drilldown\PatientDirectoryEntry;
use OpenEMR\FQHC\Reporting\ReportingPatient;
use OpenEMR\FQHC\Reporting\ReportingPatientSource;
use OpenEMR\FQHC\Reporting\Table5ReportBuilder;
use OpenEMR\FQHC\Reporting\Table5VisitRecord;
use OpenEMR\FQHC\Reporting\UdsPatientCharacteristicsReport;
use OpenEMR\FQHC\Reporting\UdsReportGenerator;
use OpenEMR\FQHC\Reporting\UdsReportPresenter;
use OpenEMR\FQHC\Reporting\UdsServiceCategory;
use OpenEMR\FQHC\SpecialPopulation\HomelessStatus;
use OpenEMR\FQHC\SpecialPopulation\SpecialPopulation;
use OpenEMR\FQHC\SpecialPopulation\SpecialPopulationStatus;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-type DrilldownPatient array{pid: int, name: string, dob: string|null}
 * @phpstan-type DrilldownCell array{label: string, count: int, patients: list<DrilldownPatient>}
 */
final class PatientCharacteristicsDrilldownTest extends TestCase
{
    public function testEveryTableDrilldownReconcilesWithItsReportedTotals(): void
    {
        $view = (new UdsReportPresenter())->present($this->report(), $this->directory());

        // Each table's drill-down accounts for exactly the patients the table
        // counts — the drill-down never invents or drops a patient.
        self::assertSame(3, $this->drilldownTotal($view['drilldown']['ageSex']));
        self::assertSame(3, $this->drilldownTotal($view['drilldown']['income']));
        self::assertSame(3, $this->drilldownTotal($view['drilldown']['insurance']));
        self::assertSame(3, $this->drilldownTotal($view['drilldown']['race']));
        self::assertSame(3, $this->drilldownTotal($view['drilldown']['zip']));

        // Every emitted cell is non-empty and its count equals its patient list.
        foreach ($view['drilldown'] as $cells) {
            foreach ($cells as $cell) {
                self::assertGreaterThan(0, $cell['count']);
                self::assertCount($cell['count'], $cell['patients']);
            }
        }
    }

    public function testUnclassifiedPayerDrillsIntoNoneUninsured(): void
    {
        $view = (new UdsReportPresenter())->present($this->report(), $this->directory());

        // p3 has no insurance type: it must appear in the None/Uninsured cell,
        // matching the aggregator's "no unknown-insurance line" coercion.
        $cell = $this->cell($view['drilldown']['insurance'], 'None / uninsured · 18 and over');
        self::assertSame(1, $cell['count']);
        self::assertSame(3, $cell['patients'][0]['pid']);
    }

    public function testLanguageAndHispanicRollupDrilldowns(): void
    {
        $view = (new UdsReportPresenter())->present($this->report(), $this->directory());

        // p1 (Spanish, interpreter) is the only language-barrier patient.
        self::assertSame(1, $this->drilldownTotal($view['drilldown']['language']));

        // p1 is Hispanic white; the five Hispanic sub-columns fold into one cell.
        $cell = $this->cell($view['drilldown']['race'], 'White · Hispanic / Latino');
        self::assertSame(1, $cell['count']);
        self::assertSame('Ramos, Ana', $cell['patients'][0]['name']);
    }

    public function testSpecialPopulationsDrilldownSplitsBreakoutsFromTotals(): void
    {
        $view = (new UdsReportPresenter())->present($this->report(), $this->directory());
        $cells = $view['drilldown']['specialPopulations'];

        // p3 is a sheltered-homeless veteran: it lands in the homeless subtype,
        // the homeless total, and the veterans line — once each.
        self::assertSame(1, $this->cell($cells, 'Homeless — ' . HomelessStatus::Shelter->label())['count']);
        self::assertSame(1, $this->cell($cells, 'Total homeless')['count']);
        self::assertSame(1, $this->cell($cells, 'Veterans')['count']);
        self::assertSame(3, $this->cell($cells, 'Total homeless')['patients'][0]['pid']);
    }

    public function testDrilldownResolvesNamesAndFallsBackToPidWhenUnknown(): void
    {
        // A directory that names p1 and p2 but not p3.
        $directory = new PatientDirectory([
            1 => new PatientDirectoryEntry(1, 'Ramos, Ana', '2015-03-01'),
            2 => new PatientDirectoryEntry(2, 'Chen, Bo', '1985-06-15'),
        ]);
        $view = (new UdsReportPresenter())->present($this->report(), $directory);

        $income = $this->cell($view['drilldown']['income'], '100% and below');
        self::assertSame('Ramos, Ana', $income['patients'][0]['name']);
        self::assertSame('2015-03-01', $income['patients'][0]['dob']);

        // p3 is absent from the directory, so it shows by id, never hidden.
        $veterans = $this->cell($view['drilldown']['specialPopulations'], 'Veterans');
        self::assertSame('Patient #3', $veterans['patients'][0]['name']);
        self::assertNull($veterans['patients'][0]['dob']);
    }

    public function testTable5DrilldownListsWithinCategoryPatients(): void
    {
        $report = (new Table5ReportBuilder())->build([
            new Table5VisitRecord(1, UdsServiceCategory::Medical, false),
            new Table5VisitRecord(1, UdsServiceCategory::Medical, true),
            new Table5VisitRecord(2, UdsServiceCategory::Medical, false),
            new Table5VisitRecord(2, UdsServiceCategory::Dental, false),
        ]);

        $view = (new UdsReportPresenter())->table5($report);

        // Medical: two patients (p1 seen twice is unduplicated); Dental: one.
        $medical = $this->cell($view['drilldown'], 'Medical');
        self::assertSame(2, $medical['count']);
        self::assertSame([1, 2], array_column($medical['patients'], 'pid'));
        self::assertSame(1, $this->cell($view['drilldown'], 'Dental')['count']);
    }

    private function directory(): PatientDirectory
    {
        return new PatientDirectory([
            1 => new PatientDirectoryEntry(1, 'Ramos, Ana', '2015-03-01'),
            2 => new PatientDirectoryEntry(2, 'Chen, Bo', '1985-06-15'),
            3 => new PatientDirectoryEntry(3, 'Okoye, Zara', '1954-11-20'),
        ]);
    }

    private function report(): UdsPatientCharacteristicsReport
    {
        $source = $this->sourceOf(
            // p1: age 10, female, Hispanic white, Spanish speaker, Medicaid, 02118
            $this->patient(1, 10, 'Female', 'white', 'hisp_or_latin', 'spanish', 'yes', FplBand::AtOrBelow100, '02118', 3),
            // p2: age 40, male, non-Hispanic Chinese, English, Medicare, 10001
            $this->patient(2, 40, 'Male', 'chinese', 'not_hisp_or_latin', 'english', 'no', FplBand::Above200, '10001', 2),
            // p3: age 70, female, non-Hispanic white, no insurance, sheltered-homeless veteran, 02118
            $this->patient(3, 70, 'Female', 'white', 'not_hisp_or_latin', 'english', 'no', FplBand::From151To200, '02118', null, [
                new SpecialPopulationStatus(SpecialPopulation::Homeless, HomelessStatus::Shelter->value),
                new SpecialPopulationStatus(SpecialPopulation::Veteran),
            ]),
        );

        return (new UdsReportGenerator($source))->generateForYear(2025);
    }

    /**
     * @param list<DrilldownCell> $cells
     */
    private function drilldownTotal(array $cells): int
    {
        return array_sum(array_column($cells, 'count'));
    }

    /**
     * @param list<DrilldownCell> $cells
     * @return DrilldownCell
     */
    private function cell(array $cells, string $label): array
    {
        foreach ($cells as $cell) {
            if ($cell['label'] === $label) {
                return $cell;
            }
        }

        self::fail('No drill-down cell labelled "' . $label . '"');
    }

    private function sourceOf(ReportingPatient ...$patients): ReportingPatientSource
    {
        return new class(array_values($patients)) implements ReportingPatientSource {
            /**
             * @param list<ReportingPatient> $patients
             */
            public function __construct(private readonly array $patients)
            {
            }

            public function cohortForYear(int $year): array
            {
                return array_map(static fn(ReportingPatient $patient): int => $patient->pid, $this->patients);
            }

            public function load(int $pid, int $year): ReportingPatient
            {
                foreach ($this->patients as $patient) {
                    if ($patient->pid === $pid) {
                        return $patient;
                    }
                }

                throw new \RuntimeException('Unknown pid ' . $pid);
            }
        };
    }

    /**
     * @param list<SpecialPopulationStatus> $specialPopulations
     */
    private function patient(
        int $pid,
        int $ageYears,
        string $sexCode,
        string $raceCode,
        string $ethnicityCode,
        string $languageCode,
        string $interpreterNeeded,
        FplBand $incomeBand,
        string $zip,
        ?int $insuranceTypeCode,
        array $specialPopulations = [],
    ): ReportingPatient {
        return new ReportingPatient(
            pid: $pid,
            ageYears: $ageYears,
            sexCode: $sexCode,
            raceCode: $raceCode,
            ethnicityCode: $ethnicityCode,
            languageCode: $languageCode,
            interpreterNeeded: $interpreterNeeded,
            zip: $zip,
            incomeBand: $incomeBand,
            insuranceTypeCode: $insuranceTypeCode,
            specialPopulations: $specialPopulations,
        );
    }
}
