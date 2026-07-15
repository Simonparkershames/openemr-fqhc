<?php

/**
 * FQHC module — provider workspace (issue #38).
 *
 * The provider role's home: today's schedule for the logged-in clinician with
 * each patient's live rooming status, the encounters opened today still
 * awaiting a note, results that have come back for review, and the care gaps
 * across the day's panel — including the UDS clinical-measure reminders that
 * connect the daily loop to the compliance story. Every deep link lands on a
 * certified surface (the encounter screen, the chart, the procedures inbox);
 * the workspace itself is read-only.
 *
 * The day comes from the certified calendar code (fetchAppointments, as on the
 * front-desk and rooming workspaces), filtered to this provider. Superglobals
 * and the legacy CDR engine are touched only here at the entry point and
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
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Core\Header;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\FQHC\DesignSystem\DesignSystemAssets;
use OpenEMR\FQHC\FrontDesk\FrontDeskDayBuilder;
use OpenEMR\FQHC\FrontDesk\FrontDeskScheduleRepository;
use OpenEMR\FQHC\Provider\CareGap;
use OpenEMR\FQHC\Provider\CareGapPanelBuilder;
use OpenEMR\FQHC\Provider\OpenEncounter;
use OpenEMR\FQHC\Provider\OpenEncounterRepository;
use OpenEMR\FQHC\Provider\PanelPatient;
use OpenEMR\FQHC\Provider\ProviderDayBuilder;
use OpenEMR\FQHC\Provider\ProviderScheduleEntry;
use OpenEMR\FQHC\Provider\ResultReviewRepository;
use OpenEMR\FQHC\Provider\ResultToReview;
use OpenEMR\FQHC\Rooming\RoomingContextRepository;
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

// The provider is the logged-in user; the id is read at the boundary and
// parsed to an int for the calendar/encounter provider filters.
$authUserId = $session->get('authUserID');
$providerId = is_numeric($authUserId) ? (int) $authUserId : 0;

$today = new DateTimeImmutable('today');
$date = $today->format('Y-m-d');

// The certified calendar owns the day query (recurrence expansion, filters).
require_once $globals->getString('srcdir') . '/appointments.inc.php';
$legacyAppointments = fetchAppointments($date, $date);
$day = (new FrontDeskDayBuilder())->build(
    $date,
    (new FrontDeskScheduleRepository())->rowsFromAppointments(
        is_array($legacyAppointments) ? $legacyAppointments : [],
    ),
);

$contextRepository = new RoomingContextRepository();
$providerDay = (new ProviderDayBuilder())->build(
    $day,
    $providerId,
    $contextRepository->roomLabelsByEventId(array_map(
        static fn(object $appointment): int => $appointment->eventId,
        $day->appointments,
    )),
    $contextRepository->encountersByEventId($date),
);

// Care gaps across the provider's panel — the patients on today's schedule.
// The CDR engine is per patient and evaluates every active rule, so it is
// limited to that panel, exactly as the rooming workspace scopes it.
$panelPatients = [];
$seenPids = [];
foreach ($providerDay->entries as $entry) {
    $pid = $entry->appointment->pid;
    if (isset($seenPids[$pid])) {
        continue;
    }
    $seenPids[$pid] = true;
    $panelPatients[] = new PanelPatient($pid, $entry->appointment->patientName);
}

$cdrEnabled = $globals->getBoolean('enable_cdr') && $globals->getBoolean('enable_cdr_crw');
$screeningsByPid = [];
if ($cdrEnabled && $panelPatients !== []) {
    require_once $globals->getString('srcdir') . '/clinical_rules.php';
    require_once $globals->getString('srcdir') . '/options.inc.php';
    $screeningFactory = new ScreeningDueFactory();
    foreach ($panelPatients as $patient) {
        $screenings = [];
        // Provider 0 = entire clinic (legacy "blank" semantics of test_rules_clinic).
        $actions = test_rules_clinic(0, 'passive_alert', $date . ' 00:00:00', 'reminders-due', $patient->pid);
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
        $screeningsByPid[$patient->pid] = $screenings;
    }
}

$careGaps = (new CareGapPanelBuilder())->build($panelPatients, $screeningsByPid);
$openEncounters = (new OpenEncounterRepository())->openForProviderOnDate($providerId, $date);
$results = (new ResultReviewRepository())->pendingReviewForProvider($providerId);

$chartUrl = static fn(int $pid): string
    => $webroot . '/interface/patient_file/summary/demographics.php?set_pid=' . $pid;
$encounterUrl = static fn(int $pid, int $encounterId): string
    => $webroot . '/interface/patient_file/summary/demographics.php?set_pid=' . $pid
        . '&set_encounterid=' . $encounterId;

$scheduleView = array_map(
    static function (ProviderScheduleEntry $entry) use ($chartUrl, $encounterUrl): array {
        $appointment = $entry->appointment;

        return [
            'pid' => $appointment->pid,
            'patientName' => $appointment->patientName,
            'timeDisplay' => $appointment->timeDisplay,
            'categoryName' => $appointment->categoryName,
            'statusTitle' => $appointment->statusTitle,
            'phaseLabel' => $appointment->phase->label(),
            'phaseVariant' => $appointment->phase->badgeVariant(),
            'roomLabel' => $entry->roomLabel,
            'readyForProvider' => $entry->isReadyForProvider(),
            'chartUrl' => $chartUrl($appointment->pid),
            'encounterUrl' => $entry->encounterId !== null
                ? $encounterUrl($appointment->pid, $entry->encounterId)
                : null,
        ];
    },
    $providerDay->entries,
);

$openEncounterView = array_map(
    static fn(OpenEncounter $encounter): array => [
        'patientName' => $encounter->patientName,
        'timeDisplay' => $encounter->timeDisplay,
        'reason' => $encounter->reason,
        'encounterUrl' => $encounterUrl($encounter->pid, $encounter->encounterId),
    ],
    $openEncounters,
);

$resultView = array_map(
    static fn(ResultToReview $result): array => [
        'patientName' => $result->patientName,
        'testName' => $result->testName,
        'reportedDisplay' => $result->reportedDisplay,
        'isAbnormal' => $result->isAbnormal,
        'chartUrl' => $chartUrl($result->pid),
    ],
    $results,
);

$careGapView = array_map(
    static fn(CareGap $gap): array => [
        'patientName' => $gap->patientName,
        'label' => $gap->screening->label,
        'statusLabel' => $gap->screening->status->label(),
        'variant' => $gap->screening->status->badgeVariant(),
        'chartUrl' => $chartUrl($gap->pid),
    ],
    $careGaps,
);

$content = (new TwigContainer(__DIR__ . '/../templates', $globals->getKernel()))
    ->getTwig()
    ->render('fqhc/provider.html.twig', [
        'dateDisplay' => $today->format('l, F j, Y'),
        'hasProvider' => $providerId > 0,
        'schedule' => $scheduleView,
        'scheduledCount' => $providerDay->total(),
        'readyCount' => $providerDay->readyCount(),
        'checkedOutCount' => $providerDay->checkedOutCount(),
        'openEncounters' => $openEncounterView,
        'results' => $resultView,
        'careGaps' => $careGapView,
        'cdrEnabled' => $cdrEnabled,
        'messagesUrl' => $webroot . '/interface/main/messages/messages.php?form_active=1',
    ]);
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Provider Workspace'); ?></title>
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
