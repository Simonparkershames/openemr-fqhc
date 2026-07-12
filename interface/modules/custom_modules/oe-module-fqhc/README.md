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

The page previews the shape of the upcoming **UDS Patient Snapshot** (#14):
reused demographics shown as data, new UDS fields shown as empty-states.

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

## Architecture notes

- **Domain/services** live in the core tree under `OpenEMR\FQHC\`
  (`src/FQHC/...`) so they are PSR-4 autoloaded, PHPStan-analyzed, and
  unit-testable in isolation. This module holds **packaging + UI** only.
- **Web Components islands** on a server-rendered Twig shell — the documented
  UI approach (see `docs/fqhc/UX-MODERNIZATION.md`). No SPA build step.
- Tokens are CSS custom properties so they cascade into Shadow DOM; component
  styles are encapsulated and cannot break (or be broken by) legacy CSS.

## Tests

A smoke test lives at `tests/Tests/Isolated/FQHC/DesignSystemAssetsTest.php`
(runs without Docker/DB):

```bash
composer phpunit-isolated -- --filter DesignSystemAssets
```
