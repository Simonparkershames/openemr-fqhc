<?php

/**
 * FQHC module — FQHC Workspace home page (epic #6, first slice).
 *
 * The landing surface for the top-level "FQHC" menu item: a modern, responsive
 * workspace that gathers the FQHC tools already built (Patient Snapshot, UDS
 * Report, Eligibility Worklist) and surfaces a live UDS data-health metric for
 * the most recently completed reporting year, so problems are visible year-round
 * rather than only at reporting time.
 *
 * A role-aware variant (provider/nurse/front-desk workspaces) builds on this
 * shell later; this slice is the shared home every role starts from. It reuses
 * the reporting services (epic #4) and the design system — nothing here touches
 * a certified code path.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../globals.php';

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Core\Header;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\FQHC\DesignSystem\DesignSystemAssets;
use OpenEMR\FQHC\Reporting\DataQuality\DataQualityWorklistGenerator;
use OpenEMR\FQHC\Reporting\DataQuality\DataQualityWorklistPresenter;
use OpenEMR\FQHC\Reporting\ReportingPatientRepository;

if (!AclMain::aclCheckCore('patients', 'demo')) {
    echo xlt('Access denied');
    exit;
}

$globals = OEGlobalsBag::getInstance();
$publicBaseUrl = $globals->getString('webroot') . '/interface/modules/custom_modules/oe-module-fqhc/public';
$assets = new DesignSystemAssets(__DIR__, $publicBaseUrl);

// Data-health headline covers the most recently completed calendar year — the
// same default the UDS report and worklist pages use.
$year = (int) date('Y') - 1;

$worklist = (new DataQualityWorklistGenerator(new ReportingPatientRepository()))->generateForYear($year);
$view = (new DataQualityWorklistPresenter())->present($worklist);

// Absolute URLs to the sibling FQHC tools this workspace links out to.
$links = [
    'snapshot' => $publicBaseUrl . '/index.php',
    'report' => $publicBaseUrl . '/report.php',
    'worklist' => $publicBaseUrl . '/eligibility-worklist.php',
];

$content = (new TwigContainer(__DIR__ . '/../templates', $globals->getKernel()))
    ->getTwig()
    ->render('fqhc/home.html.twig', [
        'year' => $year,
        'worklist' => $view,
        'links' => $links,
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
