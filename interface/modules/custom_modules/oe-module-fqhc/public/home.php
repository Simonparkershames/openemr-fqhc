<?php

/**
 * FQHC module — role workspace home (issue #33).
 *
 * Resolves the logged-in user to a workspace via the workspace registry
 * (per-user override first, then certified ACL group membership) and routes
 * each role to its home. Front desk, clinical support, and provider have
 * purpose-built pages and redirect there; the manager/quality role (and any
 * user that resolves to no role) renders here.
 *
 * The manager/quality home (issue #39) is the center's UDS-readiness glance:
 * a live data-health metric with its open-gap breakdown from the eligibility
 * worklist, a year-over-year utilization snapshot from the UDS Table 5
 * service lines, and shortcuts into the report, worklist, and snapshot.
 *
 * Session state (username) is read here at the entry point and parsed into
 * typed values passed to the domain layer — superglobals do not leak past
 * this boundary.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../globals.php';

use OpenEMR\Common\Acl\AclExtended;
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Core\Header;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\FQHC\DesignSystem\DesignSystemAssets;
use OpenEMR\FQHC\Reporting\DataQuality\DataQualityWorklistGenerator;
use OpenEMR\FQHC\Reporting\DataQuality\DataQualityWorklistPresenter;
use OpenEMR\FQHC\Reporting\ReportingPatientRepository;
use OpenEMR\FQHC\Reporting\Table5ReportGenerator;
use OpenEMR\FQHC\Reporting\Table5VisitRepository;
use OpenEMR\FQHC\Reporting\UtilizationComparison;
use OpenEMR\FQHC\Workspace\WorkspaceCard;
use OpenEMR\FQHC\Workspace\WorkspaceGlobals;
use OpenEMR\FQHC\Workspace\WorkspaceRegistry;
use OpenEMR\FQHC\Workspace\WorkspaceResolver;
use OpenEMR\FQHC\Workspace\WorkspaceRole;

if (!AclMain::aclCheckCore('patients', 'demo')) {
    echo xlt('Access denied');
    exit;
}

$globals = OEGlobalsBag::getInstance();
$webroot = $globals->getString('webroot');
$publicBaseUrl = $webroot . '/interface/modules/custom_modules/oe-module-fqhc/public';
$assets = new DesignSystemAssets(__DIR__, $publicBaseUrl);

$session = SessionWrapperFactory::getInstance()->getActiveSession();
$authUser = $session->get('authUser');
$username = is_string($authUser) ? $authUser : '';

$groupTitles = [];
if ($username !== '') {
    $rawTitles = AclExtended::aclGetGroupTitles($username);
    if (is_array($rawTitles)) {
        foreach ($rawTitles as $rawTitle) {
            if (is_string($rawTitle)) {
                $groupTitles[] = $rawTitle;
            }
        }
    }
}

// The override global is user-editable, so the bag already carries the
// logged-in user's value (interface/globals.php applies user_settings rows).
$override = $globals->getString(WorkspaceGlobals::WORKSPACE_OVERRIDE);

$registry = new WorkspaceRegistry();
$role = (new WorkspaceResolver())->resolve($override, $groupTitles);
$workspace = $role !== null ? $registry->forRole($role) : $registry->defaultWorkspace();

// Roles with a purpose-built home route there (front desk #36, clinical
// support #37, provider #38); the shared card-grid template below serves the
// roles whose dedicated workspaces haven't landed yet.
if ($workspace->role === WorkspaceRole::FrontDesk) {
    header('Location: ' . $publicBaseUrl . '/frontdesk.php');
    exit;
}
if ($workspace->role === WorkspaceRole::ClinicalSupport) {
    header('Location: ' . $publicBaseUrl . '/rooming.php');
    exit;
}
if ($workspace->role === WorkspaceRole::Provider) {
    header('Location: ' . $publicBaseUrl . '/provider.php');
    exit;
}

$cards = array_map(
    static fn(WorkspaceCard $card): array => [
        'title' => $card->title,
        'description' => $card->description,
        'url' => $webroot . $card->url,
        'ctaLabel' => $card->ctaLabel,
        'icon' => $card->icon->value,
    ],
    $workspace->cards,
);

// Every role with a purpose-built home has redirected above, so the only
// workspace still rendered by the shared card-grid template is the
// manager/quality home (issue #39) — the center's UDS-readiness glance:
// the data-health metric with its gap breakdown, a year-over-year
// utilization snapshot, and the report shortcuts.
//
// A UDS report covers a completed calendar year, so default to last year;
// the prior year is the utilization comparison baseline.
$reportingYear = (int) date('Y') - 1;
$priorYear = $reportingYear - 1;

$worklist = (new DataQualityWorklistGenerator(new ReportingPatientRepository()))
    ->generateForYear($reportingYear);
$worklistView = (new DataQualityWorklistPresenter())->present($worklist);

// Only gap types that actually occur — a summary, not a row of zeros.
$gapCounts = array_values(array_filter(
    $worklistView['gapCounts'],
    static fn(array $gapCount): bool => $gapCount['count'] > 0,
));

$dataHealth = [
    'year' => $reportingYear,
    'total' => $worklistView['total'],
    'gapCounts' => $gapCounts,
    'worklistUrl' => $publicBaseUrl . '/eligibility-worklist.php?year=' . $reportingYear,
];

$table5Generator = new Table5ReportGenerator(new Table5VisitRepository());
$utilization = new UtilizationComparison(
    $reportingYear,
    $priorYear,
    $table5Generator->generateForYear($reportingYear),
    $table5Generator->generateForYear($priorYear),
);
$utilizationCategories = [];
foreach ($utilization->categories() as $row) {
    if (!$row->hasActivity()) {
        continue;
    }
    $utilizationCategories[] = [
        'label' => $row->category->label(),
        'current' => $row->currentVisits,
        'prior' => $row->priorVisits,
        'delta' => $row->delta(),
    ];
}
$utilizationView = [
    'year' => $utilization->year,
    'priorYear' => $utilization->priorYear,
    'currentVisits' => $utilization->currentVisits(),
    'priorVisits' => $utilization->priorVisits(),
    'visitsDelta' => $utilization->visitsDelta(),
    'hasActivity' => $utilization->hasActivity(),
    'categories' => $utilizationCategories,
];

$content = (new TwigContainer(__DIR__ . '/../templates', $globals->getKernel()))
    ->getTwig()
    ->render('fqhc/home.html.twig', [
        'workspace' => [
            'roleKey' => $workspace->role->value,
            'roleLabel' => $workspace->role->label(),
            'heading' => $workspace->heading,
            'icon' => $workspace->icon->value,
            'tagline' => $workspace->tagline,
        ],
        'cards' => $cards,
        'dataHealth' => $dataHealth,
        'utilization' => $utilizationView,
    ]);
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('FQHC Workspace'); ?></title>
    <?php Header::setupHeader(['common']); ?>
    <?php foreach ($assets->styleUrls() as $styleUrl) { ?>
        <link rel="stylesheet" href="<?php echo attr($styleUrl); ?>">
    <?php } ?>
</head>
<body class="body_top">
    <?php echo $content; ?>
    <?php foreach ($assets->scriptUrls() as $scriptUrl) { ?>
        <script type="module" src="<?php echo attr($scriptUrl); ?>"></script>
    <?php } ?>
</body>
</html>
