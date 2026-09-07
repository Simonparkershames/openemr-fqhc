<?php

/**
 * The canonical cell keys for the UDS patient-characteristics drill-down.
 *
 * Both sides of the drill-down derive their keys here so they can never drift:
 * the roster builder keys a patient into a cell from that patient's parsed
 * record, and the presenter reads the same cell back when it iterates the
 * report's rows and columns. A key is opaque — its only contract is that the
 * same logical cell always produces the same string.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Reporting\Drilldown;

use OpenEMR\FQHC\Fpl\FplBand;
use OpenEMR\FQHC\Payer\UdsPayerCategory;
use OpenEMR\FQHC\Reporting\UdsAgeGroup;
use OpenEMR\FQHC\Reporting\UdsEthnicityCategory;
use OpenEMR\FQHC\Reporting\UdsRaceCategory;
use OpenEMR\FQHC\Reporting\UdsServiceCategory;
use OpenEMR\FQHC\Reporting\UdsSex;
use OpenEMR\FQHC\Reporting\UdsZipInsuranceColumn;
use OpenEMR\FQHC\Reporting\ZipResidence;
use OpenEMR\FQHC\SpecialPopulation\HomelessStatus;

final class CharacteristicsCell
{
    // A key namespace, not a value object — only static key builders.

    /**
     * The Table 3B ethnicity columns the report actually renders: the Total
     * Hispanic roll-up, Not Hispanic, and Unreported. The five Hispanic
     * sub-columns all fold into the single Hispanic column, matching the
     * presenter's roll-up.
     */
    public const RACE_HISPANIC = 'hispanic';
    public const RACE_NOT_HISPANIC = 'not_hispanic';
    public const RACE_UNREPORTED = 'unreported';

    public static function ageSex(int $line, UdsSex $sex): string
    {
        return sprintf('3a|%d|%s', $line, $sex->name);
    }

    public static function raceEthnicity(UdsRaceCategory $race, string $column): string
    {
        return sprintf('3b|%s|%s', $race->value, $column);
    }

    /**
     * The Table 3B column a patient's ethnicity contributes to, folding every
     * Hispanic sub-column into the Total Hispanic roll-up.
     */
    public static function raceColumnFor(UdsEthnicityCategory $ethnicity): string
    {
        return match ($ethnicity) {
            UdsEthnicityCategory::Mexican,
            UdsEthnicityCategory::PuertoRican,
            UdsEthnicityCategory::Cuban,
            UdsEthnicityCategory::Another,
            UdsEthnicityCategory::Combined => self::RACE_HISPANIC,
            UdsEthnicityCategory::NotHispanic => self::RACE_NOT_HISPANIC,
            UdsEthnicityCategory::Unreported => self::RACE_UNREPORTED,
        };
    }

    public static function language(): string
    {
        return '3b|language';
    }

    public static function income(FplBand $band): string
    {
        return sprintf('4i|%s', $band->name);
    }

    public static function insurance(UdsPayerCategory $category, UdsAgeGroup $ageGroup): string
    {
        return sprintf('4p|%s|%s', $category->value, $ageGroup->name);
    }

    public static function agriculturalMigratory(): string
    {
        return '4s|agricultural_migratory';
    }

    public static function agriculturalSeasonal(): string
    {
        return '4s|agricultural_seasonal';
    }

    public static function agriculturalTotal(): string
    {
        return '4s|agricultural_total';
    }

    public static function homeless(HomelessStatus $status): string
    {
        return sprintf('4s|homeless|%s', $status->value);
    }

    public static function homelessTotal(): string
    {
        return '4s|homeless_total';
    }

    public static function schoolBased(): string
    {
        return '4s|school_based';
    }

    public static function veterans(): string
    {
        return '4s|veterans';
    }

    public static function publicHousing(): string
    {
        return '4s|public_housing';
    }

    public static function zip(ZipResidence $residence, UdsZipInsuranceColumn $column): string
    {
        return sprintf('zip|%s|%s', $residence->key(), $column->value);
    }

    public static function serviceCategory(UdsServiceCategory $category): string
    {
        return sprintf('t5|%s', $category->value);
    }
}
