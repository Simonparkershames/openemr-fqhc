<?php

/**
 * FQHC module bootstrap class.
 *
 * Wires the module's event subscribers — all additive, none modifying a
 * certified code path:
 *  - a top-level "FQHC" navigation item (menu event) opening the role
 *    workspace home, with the module's pages as children;
 *  - the workspace-framework global settings (globals event): the post-login
 *    landing switch (default off) and the per-user workspace override;
 *  - a post-login landing listener (tabs render event) that, when the switch
 *    is on and the user maps to a workspace role, prepends the workspace
 *    home as the initial tab after login (issue #33). Unmapped users keep
 *    today's Calendar/Messages landing.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Modules\Fqhc;

use OpenEMR\Common\Acl\AclExtended;
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Events\Globals\GlobalsInitializedEvent;
use OpenEMR\Events\Main\Tabs\RenderEvent;
use OpenEMR\FQHC\Workspace\WorkspaceGlobals;
use OpenEMR\FQHC\Workspace\WorkspaceRegistry;
use OpenEMR\FQHC\Workspace\WorkspaceResolver;
use OpenEMR\FQHC\Workspace\WorkspaceRole;
use OpenEMR\Menu\MenuEvent;
use OpenEMR\Services\Globals\GlobalSetting;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Bootstrap
{
    public const MODULE_INSTALLATION_PATH = '/interface/modules/custom_modules/oe-module-fqhc';
    private const MENU_ID = 'fqhc0';
    private const SNAPSHOT_MENU_ID = 'fqhc_snapshot0';
    private const REPORT_MENU_ID = 'fqhc_report0';
    private const ELIGIBILITY_WORKLIST_MENU_ID = 'fqhc_eligibility_worklist0';
    private const GLOBALS_SECTION = 'FQHC';

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function subscribeToEvents(): void
    {
        $this->eventDispatcher->addListener(MenuEvent::MENU_UPDATE, $this->addMenuItem(...));
        $this->eventDispatcher->addListener(GlobalsInitializedEvent::EVENT_HANDLE, $this->addGlobalSettings(...));
        $this->eventDispatcher->addListener(RenderEvent::EVENT_BODY_RENDER_PRE, $this->renderWorkspaceLanding(...));
    }

    /**
     * Append a top-level "FQHC" menu item (opening the role workspace home)
     * with the module's pages as children. Guarded so repeated dispatches
     * never duplicate the entry.
     */
    public function addMenuItem(MenuEvent $event): MenuEvent
    {
        $menu = $event->getMenu();

        foreach ($menu as $item) {
            if ($item instanceof stdClass && ($item->menu_id ?? null) === self::MENU_ID) {
                return $event;
            }
        }

        $fqhc = new stdClass();
        $fqhc->requirement = 0;
        $fqhc->target = 'fqhc';
        $fqhc->menu_id = self::MENU_ID;
        $fqhc->label = xlt('FQHC');
        $fqhc->url = self::MODULE_INSTALLATION_PATH . '/public/home.php';
        $fqhc->children = [
            $this->snapshotMenuItem(),
            $this->reportMenuItem(),
            $this->eligibilityWorklistMenuItem(),
        ];
        $fqhc->acl_req = ['patients', 'demo'];
        $fqhc->global_req = [];

        $menu[] = $fqhc;
        $event->setMenu($menu);

        return $event;
    }

    /**
     * Register the workspace-framework globals in an "FQHC" admin section.
     * Both are user-editable so individual users can opt out of the landing
     * or override their mapped workspace; defaults keep upstream behavior.
     */
    public function addGlobalSettings(GlobalsInitializedEvent $event): void
    {
        $service = $event->getGlobalsService();
        $service->createSection(self::GLOBALS_SECTION);

        $service->appendToSection(
            self::GLOBALS_SECTION,
            WorkspaceGlobals::LOGIN_LANDING,
            new GlobalSetting(
                xlt('Land on role workspace after login'),
                GlobalSetting::DATA_TYPE_BOOL,
                '0',
                xlt('Make the FQHC role workspace the initial tab after login instead of the default Calendar/Messages tabs.'),
                true,
            ),
        );

        $service->appendToSection(
            self::GLOBALS_SECTION,
            WorkspaceGlobals::WORKSPACE_OVERRIDE,
            new GlobalSetting(
                xlt('Workspace role override'),
                GlobalSetting::DATA_TYPE_TEXT,
                '',
                xlt('Force a workspace instead of the ACL-group mapping: frontdesk, clinical, provider, or manager. Leave blank to map from the user\'s ACL group.'),
                true,
            ),
        );
    }

    /**
     * Post-login landing (tabs page render). When the landing global is on
     * and the logged-in user resolves to a workspace role, prepend the
     * workspace home as the visible initial tab. The default tabs stay open
     * behind it; unmapped users and any script failure keep today's landing.
     */
    public function renderWorkspaceLanding(RenderEvent $event): void
    {
        $globals = OEGlobalsBag::getInstance();
        if (!$globals->getBoolean(WorkspaceGlobals::LOGIN_LANDING)) {
            return;
        }

        $role = $this->resolveWorkspaceRole($globals);
        if ($role === null) {
            return;
        }

        $workspace = (new WorkspaceRegistry())->forRole($role);
        $url = $globals->getString('webroot') . self::MODULE_INSTALLATION_PATH . '/public/home.php';
        $loadingTitle = xl('Loading') . ' ' . xl('FQHC Workspace');

        // Runs after the head script seeds the default tabs and before
        // knockout binds, so the workspace renders as the active first tab.
        echo '<script>
(function () {
    try {
        var tabsList = app_view_model.application_data.tabs.tabsList;
        tabsList().forEach(function (tab) { tab.visible(false); });
        tabsList.unshift(new tabStatus(' . js_escape($loadingTitle) . ', ' . js_escape($url) . ", 'fqhc', " . js_escape($workspace->heading) . ', true, true, false));
    } catch (e) {
        console.error("FQHC workspace landing skipped:", e);
    }
})();
</script>';
    }

    /**
     * Map the logged-in user to a workspace role: per-user override global
     * first (already user-resolved by the globals loader), then certified
     * ACL group membership. Null when unmapped.
     */
    private function resolveWorkspaceRole(OEGlobalsBag $globals): ?WorkspaceRole
    {
        $authUser = SessionWrapperFactory::getInstance()->getActiveSession()->get('authUser');
        if (!is_string($authUser) || $authUser === '') {
            return null;
        }

        $groupTitles = [];
        $rawTitles = AclExtended::aclGetGroupTitles($authUser);
        if (is_array($rawTitles)) {
            foreach ($rawTitles as $rawTitle) {
                if (is_string($rawTitle)) {
                    $groupTitles[] = $rawTitle;
                }
            }
        }

        return (new WorkspaceResolver())->resolve(
            $globals->getString(WorkspaceGlobals::WORKSPACE_OVERRIDE),
            $groupTitles,
        );
    }

    /**
     * The "Patient Snapshot" child item that opens the UDS snapshot page
     * (the module's original top-level target).
     */
    private function snapshotMenuItem(): stdClass
    {
        $snapshot = new stdClass();
        $snapshot->requirement = 0;
        $snapshot->target = 'fqhc-snapshot';
        $snapshot->menu_id = self::SNAPSHOT_MENU_ID;
        $snapshot->label = xlt('Patient Snapshot');
        $snapshot->url = self::MODULE_INSTALLATION_PATH . '/public/index.php';
        $snapshot->children = [];
        $snapshot->acl_req = ['patients', 'demo'];
        $snapshot->global_req = [];

        return $snapshot;
    }

    /**
     * The "UDS Report" child item that opens the reporting page.
     */
    private function reportMenuItem(): stdClass
    {
        $report = new stdClass();
        $report->requirement = 0;
        $report->target = 'fqhc-report';
        $report->menu_id = self::REPORT_MENU_ID;
        $report->label = xlt('UDS Report');
        $report->url = self::MODULE_INSTALLATION_PATH . '/public/report.php';
        $report->children = [];
        $report->acl_req = ['patients', 'demo'];
        $report->global_req = [];

        return $report;
    }

    /**
     * The "Eligibility Worklist" child item that opens the data-quality
     * worklist page (issue #28).
     */
    private function eligibilityWorklistMenuItem(): stdClass
    {
        $worklist = new stdClass();
        $worklist->requirement = 0;
        $worklist->target = 'fqhc-eligibility-worklist';
        $worklist->menu_id = self::ELIGIBILITY_WORKLIST_MENU_ID;
        $worklist->label = xlt('Eligibility Worklist');
        $worklist->url = self::MODULE_INSTALLATION_PATH . '/public/eligibility-worklist.php';
        $worklist->children = [];
        $worklist->acl_req = ['patients', 'demo'];
        $worklist->global_req = [];

        return $worklist;
    }
}
