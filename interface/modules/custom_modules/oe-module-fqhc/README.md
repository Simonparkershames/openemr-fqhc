# OpenEMR FQHC module

Adds FQHC capability to OpenEMR — UDS-oriented data capture and a modern,
responsive, role-aware UI — **layered additively** on the ONC-certified core.
See [`docs/fqhc/`](../../../../docs/fqhc/README.md) for the program goals,
architecture, and roadmap.

## Status: Step 1 — host shell + design-system foundation

This is the first pathway step (issues #10 + #12, pathway #13). It provides:

- **An installable module** (`OpenEMR\Modules\Fqhc`) that registers itself and
  adds a top-level **FQHC** menu item via the menu event — no certified code
  touched.
- **A host page** (`public/index.php`) rendering the OpenEMR shell + FQHC Twig
  content + Web Component islands.
- **The design-system foundation:**
  - `public/assets/css/tokens.css` — design tokens as CSS custom properties
    (the single source of truth for the look & feel).
  - `public/assets/css/fqhc.css` — responsive layout primitives.
  - `public/assets/js/fqhc-components.js` — dependency-free Web Components
    (`fqhc-page-header`, `fqhc-card`, `fqhc-field-row`, `fqhc-status-badge`,
    `fqhc-empty-state`).

## Role workspace framework (issue #33)

Each FQHC role gets its own workspace home, served by `public/home.php`
through the workspace registry (`OpenEMR\FQHC\Workspace\WorkspaceRegistry`):

- **Role resolution** (`WorkspaceResolver`): the per-user override global
  `fqhc_workspace_override` (`frontdesk` | `clinical` | `provider` |
  `manager`) wins; otherwise the user's certified ACL group maps
  Physicians → provider, Clinicians → clinical, Front Office → frontdesk,
  Administrators → manager. Unmapped users see the manager/quality home
  (the module's original home) when visiting the page, and keep the
  default Calendar/Messages landing at login.
- **Post-login landing**: the global `fqhc_workspace_login_landing`
  (Admin → Config → FQHC, default **off** so upstream behavior is
  unchanged) makes the user's workspace the initial tab after login. It is
  implemented via the tabs-page render event — purely additive; the default
  tabs stay open behind the workspace tab.
- Both globals are user-editable, so individual users can opt out or pick a
  different workspace under their own settings.
- The individual role workspaces (#36–#39) plug into the registry by
  replacing their starter card sets.

## Front desk workspace (issue #36)

`public/frontdesk.php` is the front-desk role's home — `home.php` routes
front-desk users there. It shows the selected day's patient appointments
(read via the certified calendar's own `fetchAppointments()`, so recurring
events and calendar filters behave exactly like the calendar) with each
patient's place in the arrival loop (expected → arrived → with care team → checked
out, from the site's `apptstat` codes via
`OpenEMR\FQHC\FrontDesk\AppointmentStatusClassifier`), plus the
**arrival-readiness gaps** to close at check-in: missing DOB or sex, no
insurance on file, and no sliding-fee income determination — the same data
the UDS eligibility worklist reads, surfaced while the patient is at the
desk. Day navigation, quick actions (calendar, flow board, new patient,
finder), and per-row deep links into the certified appointment dialog and
patient chart complete the loop; no certified screen is modified.

## MA/nurse rooming workspace (issue #37)

`public/rooming.php` is the clinical-support role's home — `home.php`
routes MAs/nurses there. Tablet-first (one card per patient, large touch
targets), it shows two time-ordered queues built from the same day/phase
services as the front-desk workspace: checked-in patients **waiting to be
roomed** and roomed patients **with the care team**. Each card carries the
point-of-care glance — active allergies and medications from the certified
`lists` table, and **screenings due** from the certified CDR engine
(`test_rules_clinic`, gated by `enable_cdr` + `enable_cdr_crw` and limited
to worklist patients). The "Room patient" button posts to
`public/rooming-action.php`, which replicates the certified flow-board
status popup exactly — encounter carry-forward, same-day auto-create on
check-in statuses, then `manage_tracker_status()` — so tracker history,
the calendar mirror, and room assignment behave identically to the flow
board. Vitals are entered on the certified encounter screen the roomed
card links to.

## Provider workspace (issue #38)

`public/provider.php` is the provider role's home — `home.php` routes
providers there. It puts the whole provider day on one design-system
surface, denser than the front-desk view as clinicians expect:

- **Today's schedule** — the day filtered to the logged-in provider (matched
  on the calendar's `pc_aid`), reusing the same day/phase services as the
  front-desk and rooming workspaces, each row carrying its live rooming
  status and a one-click path into the note (a roomed patient's open
  encounter). The schedule is a `.fqhc-table` that collapses to card-per-row
  on phones.
- **Open encounters** — encounters opened today for which this provider is
  the responsible clinician (`form_encounter.provider_id`), still awaiting a
  note. Scoped to the current day rather than a signed/unsigned flag so it
  doesn't depend on the optional esign feature.
- **Results to review** — reports that have come back for tests this provider
  ordered and are still pending review
  (`procedure_report.review_status = 'received'`), abnormal results flagged.
- **Care gaps** — due reminders across the day's panel from the certified CDR
  engine (`test_rules_clinic`, gated by `enable_cdr` + `enable_cdr_crw`),
  ordered by urgency. These are the same reminders that drive the UDS
  clinical tables, so the daily loop meets the compliance story here.

Every action is a deep link to a certified surface (the encounter screen,
the chart, the message center); the workspace itself is read-only. The pure
`ProviderDayBuilder` and `CareGapPanelBuilder` (`src/FQHC/Provider/`) keep
the filtering and ordering rules unit-testable; the SQL and CDR calls live
at the `provider.php` boundary and in the `Provider` repositories.

## Architecture notes

- **Domain/services** live in the core tree under `OpenEMR\FQHC\`
  (`src/FQHC/...`) so they are PSR-4 autoloaded, PHPStan-analyzed, and
  unit-testable in isolation. This module holds **packaging + UI** only.
- **Web Components islands** on a server-rendered Twig shell — the documented
  UI approach (see `docs/fqhc/UX-MODERNIZATION.md`). No SPA build step.
- Tokens are CSS custom properties so they cascade into Shadow DOM; component
  styles are encapsulated and cannot break (or be broken by) legacy CSS.

## Demo seed pack (issue #35)

A fresh install is an empty database — blank calendar, zero patients, a UDS
report of zeros. The demo seed turns it into a **living clinic** so an evaluator
can log in as a role and immediately see populated workspaces, a real schedule,
and a populated UDS report.

What it seeds (deterministically, so a re-run lands the same clinic):

- **Role staff accounts** — `frontdesk`, `eligibility`, `ma`, `provider`,
  `provider2`, `billing`, `manager` — placed in the certified ACL groups
  (Front Office, Clinicians, Physicians, Accounting, Administrators). No new
  authorization is introduced.
- **~50 fictional patients** with a realistic FQHC spread: every UDS race
  roll-up line, both ethnicity columns, the full income/FPL band range, a
  Medicaid-heavy payer mix (plus Medicare, private, self-pay, and uninsured),
  and each special population (agricultural worker, homeless, public housing,
  veteran, school-based). Every patient carries a `FQHC-DEMO-###` `pubpid` and
  the `100 Demonstration Way` address so demo records are easy to spot and purge.
- **This reporting year's encounters** so UDS Tables 3A/3B/4/5 populate.
- **Today's schedule** across two providers, with patients at each check-in
  state (scheduled, arrived, roomed); arrived/roomed patients get an open
  encounter so the provider workspace has live content.
- **A deliberate minority of data-quality gaps** (missing race, missing income,
  uninsured) so the eligibility/data-quality worklist has real work to show.

### Running it (demo / evaluation installs only)

This writes fake data and creates login accounts, so it is **guarded off by
default** and must never be run in production. It requires a double opt-in and
the administrator's own password (OpenEMR verifies it before creating accounts):

```bash
FQHC_ALLOW_DEMO_SEED=1 \
FQHC_DEMO_ADMIN_PASSWORD='<admin password>' \
php bin/fqhc-seed-demo \
    --yes --admin=admin
```

Optional flags: `--site=default`, `--demo-pass='DemoPass123!'` (the shared
password for every demo account; also settable via `FQHC_DEMO_USER_PASSWORD`).
Re-running is safe — it skips anything already present and refreshes today's
schedule to the current date.

After seeding, log in as any role account (default password `DemoPass123!`) and
open **FQHC → UDS Report** to see the populated tables.

## Tests

Smoke and coverage tests live under `tests/Tests/Isolated/FQHC/` (run without
Docker/DB):

```bash
composer phpunit-isolated -- --filter DesignSystemAssets
composer phpunit-isolated -- --filter DemoDataSet
```

`DemoDataSetTest` asserts the demo panel actually spans every UDS bucket, payer
category, income band, and special population, keeps data-quality gaps a
minority, and is deterministic.
