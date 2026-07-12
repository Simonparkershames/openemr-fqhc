<?php

/**
 * FQHC demo seed — command-line entry point (issue #35).
 *
 * Populates a fresh install with the demo clinic: role staff accounts, ~50
 * fictional patients with the demographics/income/coverage/special-population
 * spread the UDS report needs, this reporting year's encounters, and today's
 * schedule. Idempotent — safe to re-run; it refreshes today's schedule and
 * skips anything already seeded.
 *
 * This writes obviously-fake data and creates login accounts, so it is a
 * demo/evaluation tool and is guarded off by default. To run it you must both
 * set FQHC_ALLOW_DEMO_SEED=1 and pass --yes, and supply an administrator whose
 * password OpenEMR verifies before any account is created. Never enable it on a
 * production system.
 *
 * Usage (from the repository root, inside the app container):
 *   FQHC_ALLOW_DEMO_SEED=1 \
 *   FQHC_DEMO_ADMIN_PASSWORD='<admin password>' \
 *   php interface/modules/custom_modules/oe-module-fqhc/bin/seed-demo.php \
 *       --yes --admin=admin [--site=default] [--demo-pass='DemoPass123!']
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

use OpenEMR\Common\Auth\AuthUtils;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\FQHC\Demo\DemoDataSeeder;
use OpenEMR\FQHC\Demo\DemoDataSet;
use OpenEMR\FQHC\Demo\SystemClock;
use OpenEMR\FQHC\Income\PatientIncomeRepository;
use OpenEMR\FQHC\SpecialPopulation\PatientSpecialPopulationRepository;
use OpenEMR\Services\AppointmentService;
use OpenEMR\Services\EncounterService;
use OpenEMR\Services\InsuranceCompanyService;
use OpenEMR\Services\InsuranceService;
use OpenEMR\Services\PatientService;
use OpenEMR\Services\UserService;

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

// Parse options with getopt() (reads argv internally, so this stays a clean
// CLI boundary): --yes is a flag; the rest take values.
$options = getopt('', ['yes', 'site:', 'admin:', 'admin-pass:', 'demo-pass:']);
$options = is_array($options) ? $options : [];

$optString = static function (array $options, string $key, string $default): string {
    $value = $options[$key] ?? null;
    return is_string($value) ? $value : $default;
};

$fail = static function (string $message): never {
    fwrite(STDERR, "\n  ✗ " . $message . "\n\n");
    exit(1);
};

// --- Double opt-in guard: this is never something to run by accident. ---
if (getenv('FQHC_ALLOW_DEMO_SEED') !== '1') {
    $fail(
        "Refusing to run: set FQHC_ALLOW_DEMO_SEED=1 to confirm this is a demo/"
        . "evaluation install.\nThis command writes fake patients and creates login accounts. "
        . "Never run it in production.",
    );
}
if (!array_key_exists('yes', $options)) {
    $fail('Refusing to run without --yes (confirms you intend to seed demo data).');
}

$site = $optString($options, 'site', 'default');
$adminUsername = $optString($options, 'admin', 'admin');

$adminPassword = $optString($options, 'admin-pass', getenv('FQHC_DEMO_ADMIN_PASSWORD') ?: '');
if ($adminPassword === '') {
    $fail(
        "Administrator password required to create demo accounts.\n"
        . 'Set FQHC_DEMO_ADMIN_PASSWORD or pass --admin-pass=... (the password of the --admin user).',
    );
}

$demoPassword = $optString($options, 'demo-pass', getenv('FQHC_DEMO_USER_PASSWORD') ?: 'DemoPass123!');

// --- Bootstrap OpenEMR for the requested site. ---
// globals.php resolves the multisite id from $_GET['site'] (its $_SERVER host
// fallback is unavailable under CLI). Setting it here is the established
// CLI-entry-point pattern used across contrib/util/*.cli.php; this is the
// outermost boundary, before any application code runs.
$_GET['site'] = $site;
$ignoreAuth = true;
$sessionAllowWrite = true;
require_once __DIR__ . '/../../../../globals.php';

// Establish the acting administrator in the session so the ACL check behind
// user creation resolves against a real admin identity.
$adminUserId = QueryUtils::fetchSingleValue(
    'SELECT id FROM users WHERE username = ? LIMIT 1',
    'id',
    [$adminUsername],
);
if (!is_numeric($adminUserId)) {
    $fail('Administrator user "' . $adminUsername . '" not found.');
}
$adminUserId = (int) $adminUserId;

$_SESSION['authUser'] = $adminUsername;
$_SESSION['authUserID'] = $adminUserId;
$_SESSION['authProvider'] = QueryUtils::fetchSingleValue(
    'SELECT name FROM `groups` WHERE user = ? LIMIT 1',
    'name',
    [$adminUsername],
) ?? 'Default';

echo "\nSeeding the FQHC demo clinic (site: {$site})…\n";

$seeder = new DemoDataSeeder(
    new DemoDataSet(),
    new SystemClock(),
    new PatientService(),
    new AppointmentService(),
    new EncounterService(),
    new InsuranceCompanyService(),
    new InsuranceService(),
    new UserService(),
    new PatientIncomeRepository(),
    new PatientSpecialPopulationRepository(),
    new AuthUtils(),
);

$result = $seeder->seed($adminUserId, $adminPassword, $demoPassword);

echo "\nDone.\n";
echo sprintf("  Users:        %d created, %d already present\n", $result->usersCreated, $result->usersSkipped);
echo sprintf("  Patients:     %d created, %d already present\n", $result->patientsCreated, $result->patientsSkipped);
echo sprintf("  Insurance:    %d coverage rows\n", $result->insuranceRowsCreated);
echo sprintf("  Encounters:   %d created\n", $result->encountersCreated);
echo sprintf("  Appointments: %d on today's schedule\n", $result->appointmentsCreated);

if ($result->warnings !== []) {
    echo "\nWarnings:\n";
    foreach ($result->warnings as $warning) {
        echo '  • ' . $warning . "\n";
    }
}

echo "\nDemo staff accounts (password: {$demoPassword}):\n";
foreach ((new DemoDataSet())->users() as $user) {
    echo sprintf("  %-12s %s %s (%s)\n", $user->username, $user->firstName, $user->lastName, implode(', ', $user->aclGroups));
}
echo "\n";
