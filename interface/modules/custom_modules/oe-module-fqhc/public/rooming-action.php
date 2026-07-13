<?php

/**
 * FQHC module — rooming status action (issue #37).
 *
 * POST-only endpoint behind the rooming workspace's "Room patient" button.
 * It performs exactly what the certified flow-board status popup
 * (interface/patient_tracker/patient_tracker_status.php) does: carry any
 * existing tracker encounter forward, auto-create a same-day encounter on a
 * check-in status when the site has that enabled, and write the status and
 * room through the certified manage_tracker_status() — which updates the
 * patient tracker and mirrors to the calendar event. No new write logic.
 *
 * The appointment's date, time, and patient are looked up server-side from
 * the posted event id; the posted status and room are validated against
 * their certified lists before use.
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
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Core\OEGlobalsBag;

if (!AclMain::aclCheckCore('patients', 'appt')) {
    echo xlt('Access denied');
    exit;
}

$globals = OEGlobalsBag::getInstance();
$publicBaseUrl = $globals->getString('webroot') . '/interface/modules/custom_modules/oe-module-fqhc/public';
$backUrl = $publicBaseUrl . '/rooming.php';

if (filter_input(INPUT_SERVER, 'REQUEST_METHOD') !== 'POST') {
    header('Location: ' . $backUrl);
    exit;
}

CsrfUtils::checkCsrfInput(INPUT_POST, dieOnFail: true);

require_once $globals->getString('srcdir') . '/forms.inc.php';
require_once $globals->getString('srcdir') . '/encounter_events.inc.php';
require_once $globals->getString('srcdir') . '/patient_tracker.inc.php';

$session = SessionWrapperFactory::getInstance()->getActiveSession();

// Parse and validate the posted values.
$eid = filter_input(INPUT_POST, 'eid', FILTER_VALIDATE_INT);
$statusInput = filter_input(INPUT_POST, 'statustype');
$roomInput = filter_input(INPUT_POST, 'roomnum');

$status = is_string($statusInput) ? $statusInput : '';
$statusValid = $status !== '' && is_array(QueryUtils::querySingleRow(
    "SELECT option_id FROM list_options WHERE list_id = 'apptstat' AND option_id = ? AND activity = 1",
    [$status],
));

$room = is_string($roomInput) ? $roomInput : '';
if ($room !== '') {
    $roomValid = is_array(QueryUtils::querySingleRow(
        "SELECT option_id FROM list_options WHERE list_id = 'patient_flow_board_rooms' AND option_id = ? AND activity = 1",
        [$room],
    ));
    if (!$roomValid) {
        $room = '';
    }
}

$event = null;
if (is_int($eid) && $eid > 0) {
    $event = QueryUtils::querySingleRow(
        'SELECT pc_eventDate, pc_startTime, pc_pid, pc_catid, pc_hometext, pc_aid, pc_facility, pc_billing_location '
        . 'FROM openemr_postcalendar_events WHERE pc_eid = ?',
        [$eid],
    );
}

if (!$statusValid || !is_array($event) || !is_numeric($event['pc_pid'] ?? null) || (int) $event['pc_pid'] <= 0) {
    header('Location: ' . $backUrl);
    exit;
}

$apptdate = is_string($event['pc_eventDate']) ? $event['pc_eventDate'] : '';
$appttime = is_string($event['pc_startTime']) ? $event['pc_startTime'] : '';
$pid = (int) $event['pc_pid'];

// Mirror the certified status popup: carry an existing tracker encounter
// forward; on a check-in status, same-day, with auto-create enabled and no
// encounter yet, create one from the appointment's own details.
$isTracker = is_tracker_encounter_exist($apptdate, $appttime, $pid, $eid);
if (
    $globals->get('auto_create_new_encounters')
    && $apptdate == date('Y-m-d')
    && is_checkin($status) == '1'
    && !$isTracker
) {
    $encounter = todaysEncounterCheck(
        $pid,
        $apptdate,
        $event['pc_hometext'],
        $event['pc_facility'],
        $event['pc_billing_location'],
        $event['pc_aid'],
        $event['pc_catid'],
        false,
    );
    manage_tracker_status($apptdate, $appttime, $eid, $pid, $session->get('authUser'), $status, $room, $encounter);
} else {
    manage_tracker_status($apptdate, $appttime, $eid, $pid, $session->get('authUser'), $status, $room, $isTracker);
}

header('Location: ' . $backUrl);
exit;
