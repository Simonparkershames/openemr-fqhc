<?php

/**
 * FQHC module — front desk workspace (issue #36).
 *
 * The front-desk role's home: today's appointments with each patient's place
 * in the arrival loop (expected → arrived → with care team → checked out),
 * the arrival-readiness gaps to close at check-in (demographics, insurance
 * on file, sliding-fee income determination), and quick actions into the
 * certified calendar, flow board, patient finder, and new-patient screens.
 * The post-login landing routes front-desk users here via home.php.
 *
 * The day is read from the query string at this entry point and parsed into
 * a typed value; superglobals do not leak past this boundary.
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
use OpenEMR\FQHC\FrontDesk\AppointmentPhase;
use OpenEMR\FQHC\FrontDesk\FrontDeskAppointment;
use OpenEMR\FQHC\FrontDesk\FrontDeskDayBuilder;
use OpenEMR\FQHC\FrontDesk\FrontDeskScheduleRepository;
use OpenEMR\FQHC\FrontDesk\ReadinessFlag;
use OpenEMR\FQHC\Workspace\WorkspaceCard;
use OpenEMR\FQHC\Workspace\WorkspaceRegistry;
use OpenEMR\FQHC\Workspace\WorkspaceRole;

if (!AclMain::aclCheckCore('patients', 'appt')) {
    echo xlt('Access denied');
    exit;
}

$globals = OEGlobalsBag::getInstance();
$webroot = $globals->getString('webroot');
$publicBaseUrl = $webroot . '/interface/modules/custom_modules/oe-module-fqhc/public';
$assets = new DesignSystemAssets(__DIR__, $publicBaseUrl);

// Selected day: today unless a valid ?date=Y-m-d is given.
$today = new DateTimeImmutable('today');
$day = $today;
$dateInput = filter_input(INPUT_GET, 'date');
if (is_string($dateInput)) {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $dateInput);
    if ($parsed instanceof DateTimeImmutable && $parsed->format('Y-m-d') === $dateInput) {
        $day = $parsed;
    }
}
$date = $day->format('Y-m-d');

// The certified calendar code owns the day query (recurrence expansion,
// calendar filter events); the FQHC repository only adapts its rows into
// typed values.
require_once $globals->getString('srcdir') . '/appointments.inc.php';
$legacyAppointments = fetchAppointments($date, $date);
$schedule = (new FrontDeskDayBuilder())->build(
    $date,
    (new FrontDeskScheduleRepository())->rowsFromAppointments(
        is_array($legacyAppointments) ? $legacyAppointments : [],
    ),
);

$workspace = (new WorkspaceRegistry())->forRole(WorkspaceRole::FrontDesk);
$quickActions = array_map(
    static fn(WorkspaceCard $card): array => [
        'title' => $card->title,
        'description' => $card->description,
        'url' => $webroot . $card->url,
        'ctaLabel' => $card->ctaLabel,
    ],
    $workspace->cards,
);

$summary = array_map(
    static fn(AppointmentPhase $phase): array => [
        'label' => $phase->label(),
        'count' => $schedule->countInPhase($phase),
        'variant' => $phase->badgeVariant(),
    ],
    [
        AppointmentPhase::Expected,
        AppointmentPhase::Arrived,
        AppointmentPhase::WithCareTeam,
        AppointmentPhase::CheckedOut,
    ],
);

$rows = array_map(
    static fn(FrontDeskAppointment $appointment): array => [
        'eventId' => $appointment->eventId,
        'pid' => $appointment->pid,
        'patientName' => $appointment->patientName,
        'timeDisplay' => $appointment->timeDisplay,
        'durationMinutes' => $appointment->durationMinutes,
        'providerName' => $appointment->providerName,
        'categoryName' => $appointment->categoryName,
        'statusTitle' => $appointment->statusTitle,
        'phaseLabel' => $appointment->phase->label(),
        'phaseVariant' => $appointment->phase->badgeVariant(),
        'phaseActive' => $appointment->phase->isActive(),
        'ready' => $appointment->isReady(),
        'flags' => array_map(
            static fn(ReadinessFlag $flag): string => $flag->label(),
            $appointment->readinessFlags,
        ),
    ],
    $schedule->appointments,
);

$content = (new TwigContainer(__DIR__ . '/../templates', $globals->getKernel()))
    ->getTwig()
    ->render('fqhc/frontdesk.html.twig', [
        'date' => $date,
        'dateDisplay' => $day->format('l, F j, Y'),
        'isToday' => $date === $today->format('Y-m-d'),
        'prevDateUrl' => $publicBaseUrl . '/frontdesk.php?date=' . $day->modify('-1 day')->format('Y-m-d'),
        'nextDateUrl' => $publicBaseUrl . '/frontdesk.php?date=' . $day->modify('+1 day')->format('Y-m-d'),
        'todayUrl' => $publicBaseUrl . '/frontdesk.php',
        'total' => $schedule->total(),
        'needsAttention' => $schedule->needsAttentionCount(),
        'summary' => $summary,
        'rows' => $rows,
        'quickActions' => $quickActions,
        'patientBaseUrl' => $webroot . '/interface/patient_file/summary/demographics.php',
        'appointmentBaseUrl' => $webroot . '/interface/main/calendar/add_edit_event.php',
    ]);
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Front Desk Workspace'); ?></title>
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
