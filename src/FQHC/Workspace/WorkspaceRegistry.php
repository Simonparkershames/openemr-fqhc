<?php

/**
 * Registry of role workspace homes (issue #33): role key → workspace
 * definition (heading, tagline, card set) rendered by the shared home
 * template in oe-module-fqhc.
 *
 * The manager/quality workspace generalizes the module's original home —
 * the UDS surfaces (snapshot, report, eligibility worklist) that previously
 * sat behind the single top-level FQHC menu item. The other roles start from
 * the existing core surfaces for their daily loop; their dedicated
 * workspaces land in issues #36–#38 and plug in here.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Workspace;

use OpenEMR\FQHC\DesignSystem\Icon;

final class WorkspaceRegistry
{
    private const MODULE_PUBLIC_PATH = '/interface/modules/custom_modules/oe-module-fqhc/public';

    public function forRole(WorkspaceRole $role): Workspace
    {
        return match ($role) {
            WorkspaceRole::FrontDesk => new Workspace(
                WorkspaceRole::FrontDesk,
                'Front Desk Workspace',
                'Today\'s appointments, arrival readiness, and check-in — the full arrival loop on one surface (home.php routes this role to frontdesk.php).',
                [
                    new WorkspaceCard(
                        'Calendar',
                        'Book, confirm, and manage visits on the appointment schedule.',
                        '/interface/main/main_info.php',
                        'Open calendar',
                        Icon::Calendar,
                    ),
                    new WorkspaceCard(
                        'Flow Board',
                        'Check patients in and track where they are in the visit.',
                        '/interface/patient_tracker/patient_tracker.php?skip_timeout_reset=1',
                        'Open flow board',
                        Icon::Arrived,
                    ),
                    new WorkspaceCard(
                        'New Patient',
                        'Register a new patient before their first visit.',
                        '/interface/new/new.php',
                        'Register patient',
                        Icon::Patient,
                    ),
                    new WorkspaceCard(
                        'Patient Finder',
                        'Look up an existing patient by name or chart number.',
                        '/interface/main/finder/dynamic_finder.php',
                        'Find a patient',
                        Icon::Search,
                    ),
                ],
                Icon::Appointment,
            ),
            WorkspaceRole::ClinicalSupport => new Workspace(
                WorkspaceRole::ClinicalSupport,
                'Clinical Support Workspace',
                'The rooming worklist — checked-in patients to room, vitals, and screenings due (home.php routes this role to rooming.php).',
                [
                    new WorkspaceCard(
                        'Rooming Worklist',
                        'Room checked-in patients and see allergies, meds, and screenings due.',
                        self::MODULE_PUBLIC_PATH . '/rooming.php',
                        'Open rooming',
                        Icon::Roomed,
                    ),
                    new WorkspaceCard(
                        'Flow Board',
                        'The full visit queue across every status and room.',
                        '/interface/patient_tracker/patient_tracker.php?skip_timeout_reset=1',
                        'Open flow board',
                        Icon::Arrived,
                    ),
                    new WorkspaceCard(
                        'Messages',
                        'Clinical messages and patient follow-up tasks assigned to you.',
                        '/interface/main/messages/messages.php?form_active=1',
                        'Open messages',
                        Icon::Message,
                    ),
                    new WorkspaceCard(
                        'Eligibility Worklist',
                        'Patients with UDS data-quality gaps to close during intake.',
                        self::MODULE_PUBLIC_PATH . '/eligibility-worklist.php',
                        'Open worklist',
                        Icon::Worklist,
                    ),
                ],
                Icon::Roomed,
            ),
            WorkspaceRole::Provider => new Workspace(
                WorkspaceRole::Provider,
                'Provider Workspace',
                'Your day on one surface — today\'s schedule with rooming status, encounters awaiting a note, results to review, and care gaps (home.php routes this role to provider.php).',
                [
                    new WorkspaceCard(
                        'My Day',
                        'Today\'s schedule, open encounters, results, and care gaps.',
                        self::MODULE_PUBLIC_PATH . '/provider.php',
                        'Open my day',
                        Icon::Provider,
                    ),
                    new WorkspaceCard(
                        'Messages',
                        'Results, refills, and patient messages awaiting your review.',
                        '/interface/main/messages/messages.php?form_active=1',
                        'Open messages',
                        Icon::Message,
                    ),
                    new WorkspaceCard(
                        'Patient Finder',
                        'Open a patient chart by name or chart number.',
                        '/interface/main/finder/dynamic_finder.php',
                        'Find a patient',
                        Icon::Search,
                    ),
                ],
                Icon::Provider,
            ),
            WorkspaceRole::Manager => new Workspace(
                WorkspaceRole::Manager,
                'Manager & Quality Workspace',
                'UDS reporting and data health for the center. Consolidates into a full quality home in issue #39.',
                [
                    new WorkspaceCard(
                        'UDS Report',
                        'Run and review the UDS patient-characteristics and utilization tables.',
                        self::MODULE_PUBLIC_PATH . '/report.php',
                        'Open UDS report',
                        Icon::Report,
                    ),
                    new WorkspaceCard(
                        'Eligibility Worklist',
                        'Patients with data-quality gaps that would distort UDS counts.',
                        self::MODULE_PUBLIC_PATH . '/eligibility-worklist.php',
                        'Open worklist',
                        Icon::Worklist,
                    ),
                    new WorkspaceCard(
                        'UDS Patient Snapshot',
                        'The essential UDS fields for the currently selected patient.',
                        self::MODULE_PUBLIC_PATH . '/index.php',
                        'Open snapshot',
                        Icon::Snapshot,
                    ),
                ],
                Icon::Report,
            ),
        };
    }

    /**
     * The workspace shown when no role-specific one applies — the
     * manager/quality home, which generalizes the module's original
     * single home page.
     */
    public function defaultWorkspace(): Workspace
    {
        return $this->forRole(WorkspaceRole::Manager);
    }

    /**
     * @return non-empty-list<Workspace>
     */
    public function all(): array
    {
        return array_map($this->forRole(...), WorkspaceRole::cases());
    }
}
