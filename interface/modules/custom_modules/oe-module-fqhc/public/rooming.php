<?php

/**
 * FQHC module — MA/nurse rooming workspace (issue #37).
 *
 * The clinical-support role's home: today's checked-in patients waiting to
 * be roomed and roomed patients with (or waiting for) the provider, each
 * with the clinical glance an MA needs at the point of care — active
 * allergies, active medications, and due screenings from the certified CDR
 * engine. Rooming a patient posts to rooming-action.php, which writes
 * through the same certified tracker function the flow board uses; vitals
 * are entered on the certified encounter screen the open-encounter button
 * links to. Tablet-first: card-per-patient layout, large touch targets.
 *
 * Superglobals are read only at this entry point; the CDR engine and its
 * list labels are legacy code, so they are called here at the boundary and
 * parsed into typed values immediately.
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
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Core\Header;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\FQHC\DesignSystem\DesignSystemAssets;
use OpenEMR\FQHC\FrontDesk\FrontDeskDayBuilder;
use OpenEMR\FQHC\FrontDesk\FrontDeskScheduleRepository;
use OpenEMR\FQHC\Rooming\PatientGlance;
use OpenEMR\FQHC\Rooming\PatientGlanceRepository;
use OpenEMR\FQHC\Rooming\RoomingContextRepository;
use OpenEMR\FQHC\Rooming\RoomingQueueEntry;
use OpenEMR\FQHC\Rooming\RoomingWorklistBuilder;
use OpenEMR\FQHC\Rooming\ScreeningDue;
use OpenEMR\FQHC\Rooming\ScreeningDueFactory;

if (!AclMain::aclCheckCore('patients', 'appt')) {
    echo xlt('Access denied');
    exit;
}

$globals = OEGlobalsBag::getInstance();
$webroot = $globals->getString('webroot');
$publicBaseUrl = $webroot . '/interface/modules/custom_modules/oe-module-fqhc/public';
$assets = new DesignSystemAssets(__DIR__, $publicBaseUrl);
$session = SessionWrapperFactory::getInstance()->getActiveSession();

// Rooming is inherently a "today" surface.
$today = new DateTimeImmutable('today');
$date = $today->format('Y-m-d');
// Capture the display string before any legacy include: appointments.inc.php
// pulls in encounter_events.inc.php, whose top-level `$today = date('Y-m-d')`
// clobbers this global, so anything needed from it must be read up front.
$dateDisplay = $today->format('l, F j, Y');

// The certified calendar code owns the day query (recurrence expansion,
// calendar filter events), exactly as on the front-desk workspace.
require_once $globals->getString('srcdir') . '/appointments.inc.php';
$legacyAppointments = fetchAppointments($date, $date);
$day = (new FrontDeskDayBuilder())->build(
    $date,
    (new FrontDeskScheduleRepository())->rowsFromAppointments(
        is_array($legacyAppointments) ? $legacyAppointments : [],
    ),
);

$contextRepository = new RoomingContextRepository();
$eventIds = array_map(
    static fn($appointment): int => $appointment->eventId,
    $day->appointments,
);

// Clinical glance for the patients actually on the worklist (arrived or
// roomed) — allergies/meds from the lists table, screenings from the CDR
// engine when it is enabled. The engine call is per patient and evaluates
// every active rule, so it is limited to the worklist's patients.
$worklistBuilder = new RoomingWorklistBuilder();
$phaseOnly = $worklistBuilder->build($day, [], [], []);
$worklistPids = array_values(array_unique(array_map(
    static fn(RoomingQueueEntry $entry): int => $entry->appointment->pid,
    $phaseOnly->all(),
)));

$cdrEnabled = $globals->getBoolean('enable_cdr') && $globals->getBoolean('enable_cdr_crw');
if ($cdrEnabled) {
    require_once $globals->getString('srcdir') . '/clinical_rules.php';
    require_once $globals->getString('srcdir') . '/options.inc.php';
}

$glanceRepository = new PatientGlanceRepository();
$screeningFactory = new ScreeningDueFactory();
$glanceByPid = [];
foreach ($worklistPids as $pid) {
    $screenings = [];
    if ($cdrEnabled) {
        // Provider 0 = entire clinic (legacy "blank" semantics of test_rules_clinic).
        $actions = test_rules_clinic(0, 'passive_alert', $date . ' 00:00:00', 'reminders-due', $pid);
        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }
            $category = $action['category'] ?? '';
            $item = $action['item'] ?? '';
            $categoryTitle = is_string($category) ? getListItemTitle('rule_action_category', $category) : '';
            $itemTitle = is_string($item) ? getListItemTitle('rule_action', $item) : '';
            $label = trim(
                (is_string($categoryTitle) ? $categoryTitle : '')
                . ': '
                . (is_string($itemTitle) ? $itemTitle : ''),
                ': '
            );
            $screening = $screeningFactory->fromRuleAction($action, $label);
            if ($screening instanceof ScreeningDue) {
                $screenings[] = $screening;
            }
        }
    }

    $glanceByPid[$pid] = new PatientGlance(
        $glanceRepository->activeAllergyTitles($pid),
        $glanceRepository->activeMedicationTitles($pid),
        $screenings,
    );
}

$worklist = $worklistBuilder->build(
    $day,
    $contextRepository->roomLabelsByEventId($eventIds),
    $contextRepository->encountersByEventId($date),
    $glanceByPid,
);

$entryView = static function (RoomingQueueEntry $entry) use ($webroot): array {
    $appointment = $entry->appointment;

    return [
        'eventId' => $appointment->eventId,
        'pid' => $appointment->pid,
        'patientName' => $appointment->patientName,
        'timeDisplay' => $appointment->timeDisplay,
        'providerName' => $appointment->providerName,
        'categoryName' => $appointment->categoryName,
        'statusTitle' => $appointment->statusTitle,
        'roomLabel' => $entry->roomLabel,
        'chartUrl' => $webroot . '/interface/patient_file/summary/demographics.php?set_pid=' . $appointment->pid,
        'encounterUrl' => $entry->encounterId !== null
            ? $webroot . '/interface/patient_file/summary/demographics.php?set_pid=' . $appointment->pid
                . '&set_encounterid=' . $entry->encounterId
            : null,
        'allergies' => $entry->glance->allergies,
        'medications' => $entry->glance->medications,
        'screenings' => array_map(
            static fn(ScreeningDue $screening): array => [
                'label' => $screening->label,
                'statusLabel' => $screening->status->label(),
                'variant' => $screening->status->badgeVariant(),
            ],
            $entry->glance->screeningsDue,
        ),
    ];
};

$content = (new TwigContainer(__DIR__ . '/../templates', $globals->getKernel()))
    ->getTwig()
    ->render('fqhc/rooming.html.twig', [
        'dateDisplay' => $dateDisplay,
        'awaitingRooming' => array_map($entryView, $worklist->awaitingRooming),
        'withCareTeam' => array_map($entryView, $worklist->withCareTeam),
        'roomOptions' => $contextRepository->roomOptions(),
        'cdrEnabled' => $cdrEnabled,
        'actionUrl' => $publicBaseUrl . '/rooming-action.php',
        'csrfToken' => CsrfUtils::collectCsrfToken(session: $session),
    ]);
?>
<!DOCTYPE html>
<html class="fqhc-page">
<head>
    <title><?php echo xlt('Rooming Workspace'); ?></title>
    <script><?php echo DesignSystemAssets::themeBootstrapScript(); ?></script>
    <?php Header::setupHeader(['common']); ?>
    <?php foreach ($assets->styleUrls() as $styleUrl) { ?>
        <link rel="stylesheet" href="<?php echo attr($styleUrl); ?>">
    <?php } ?>
</head>
<body class="body_top fqhc-body">
    <?php echo $content; ?>
    <?php foreach ($assets->scriptUrls() as $scriptUrl) { ?>
        <script type="module" src="<?php echo attr($scriptUrl); ?>"></script>
    <?php } ?>
</body>
</html>
