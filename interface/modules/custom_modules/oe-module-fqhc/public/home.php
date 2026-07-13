<?php

/**
 * FQHC module — role workspace home (issue #33).
 *
 * Resolves the logged-in user to a workspace via the workspace registry
 * (per-user override first, then certified ACL group membership) and renders
 * that role's home: heading, tagline, and card set. Users that resolve to no
 * role see the manager/quality workspace — the generalization of the module's
 * original home page. The manager home also shows a live UDS data-health
 * metric from the eligibility worklist.
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
use OpenEMR\FQHC\Reporting\ReportingPatientRepository;
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
// support #37); the shared card-grid template below serves the roles whose
// dedicated workspaces haven't landed yet.
if ($workspace->role === WorkspaceRole::FrontDesk) {
    header('Location: ' . $publicBaseUrl . '/frontdesk.php');
    exit;
}
if ($workspace->role === WorkspaceRole::ClinicalSupport) {
    header('Location: ' . $publicBaseUrl . '/rooming.php');
    exit;
}

$cards = array_map(
    static fn(WorkspaceCard $card): array => [
        'title' => $card->title,
        'description' => $card->description,
        'url' => $webroot . $card->url,
        'ctaLabel' => $card->ctaLabel,
    ],
    $workspace->cards,
);

// Live UDS data-health metric for the manager/quality home.
$dataHealth = null;
if ($workspace->role === WorkspaceRole::Manager) {
    $worklistYear = (int) date('Y') - 1;
    $worklist = (new DataQualityWorklistGenerator(new ReportingPatientRepository()))
        ->generateForYear($worklistYear);
    $dataHealth = [
        'year' => $worklistYear,
        'total' => $worklist->total(),
        'worklistUrl' => $publicBaseUrl . '/eligibility-worklist.php?year=' . $worklistYear,
    ];
}

$content = (new TwigContainer(__DIR__ . '/../templates', $globals->getKernel()))
    ->getTwig()
    ->render('fqhc/home.html.twig', [
        'workspace' => [
            'roleKey' => $workspace->role->value,
            'roleLabel' => $workspace->role->label(),
            'heading' => $workspace->heading,
            'tagline' => $workspace->tagline,
        ],
        'cards' => $cards,
        'dataHealth' => $dataHealth,
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
