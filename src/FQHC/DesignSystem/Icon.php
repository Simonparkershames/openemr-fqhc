<?php

/**
 * The FQHC semantic icon vocabulary.
 *
 * One case per *concept*, never per drawing. Server-side code that decides
 * what a surface is about — which card belongs to which workspace, which icon
 * a care gap carries — names the concept here and lets the design system
 * decide what it looks like. Templates then pass the case's value straight to
 * a component's `icon` attribute.
 *
 * These cases mirror the registry in `assets/js/fqhc-icons.js` exactly, and
 * `IconRegistryTest` fails if the two ever drift: a name only PHP knows would
 * render nothing, and a drawing only the browser knows is undocumented here.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\DesignSystem;

enum Icon: string
{
    // People.
    case Patient = 'patient';
    case Provider = 'provider';
    case SpecialPopulation = 'special-population';

    // The visit, in the order it happens.
    case Appointment = 'appointment';
    case Calendar = 'calendar';
    case Arrived = 'arrived';
    case CheckedIn = 'checked-in';
    case Roomed = 'roomed';
    case Encounter = 'encounter';

    // Clinical follow-through.
    case Result = 'result';
    case CareGap = 'care-gap';

    // UDS and coverage.
    case Income = 'income';
    case Payer = 'payer';
    case Report = 'report';
    case Worklist = 'worklist';
    case Snapshot = 'snapshot';

    // Wayfinding.
    case Home = 'home';
    case Message = 'message';
    case Search = 'search';
    case DesignSystem = 'design-system';
    case External = 'external';
    case Empty = 'empty';

    // Status. These are the icons the status-badge variants lead with, so the
    // names match the variant names exactly.
    case Success = 'success';
    case Warning = 'warning';
    case Danger = 'danger';
    case Info = 'info';
    case Neutral = 'neutral';
    case Pending = 'pending';
}
