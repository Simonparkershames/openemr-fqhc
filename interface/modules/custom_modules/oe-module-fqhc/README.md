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

## Living style guide (issue #59)

**FQHC → Design System** (`public/showcase.php`, admin/super only) renders the
entire design system on one page:

- **Foundations** — every token, parsed out of `assets/css/tokens.css` at render
  time by `OpenEMR\FQHC\DesignSystem\TokenSheetParser` and drawn as a live
  specimen: colour swatches, the type scale set at size, spacing steps drawn to
  width, radii, elevations, the focus ring. Add a token to `tokens.css` and it
  appears here; remove one and it disappears. Nothing to register.
- **Components** — every `fqhc-*` element in every variant *and* every state
  that is easy to forget: missing attributes, empty values, unknown badge
  variants, unbroken long content.
- **Patterns** — the card grid, data table, and form-row compositions the
  workspaces are assembled from. Narrow the window to see the responsive rules.
- **Accessibility** — `ContrastAudit` measures every foreground/background
  pairing the components actually use and prints the WCAG 2.1 ratio and rating
  beside each one. `ContrastAuditTest` asserts the same thing in CI, so a token
  edit that drops a pairing below AA fails the build rather than waiting to be
  spotted.

Build a new component here first: it is faster than seeding data and clicking
through a role loop, and it is where a reviewer will look for an inconsistency.

## Icons (issue #60)

One name per **concept**, never per drawing. Templates and server-side code ask
for `care-gap` or `worklist`; what that resolves to is decided once, in
`assets/js/fqhc-icons.js`. The same vocabulary exists in PHP as the
`OpenEMR\FQHC\DesignSystem\Icon` enum, so a `WorkspaceCard` can carry its
concept and the template just passes it through. `IconRegistryTest` fails if
the enum and the browser-side registry ever drift, or if a template names an
icon that does not exist.

```twig
<fqhc-card icon="care-gap" heading="Care gaps">…</fqhc-card>
<fqhc-icon name="patient"></fqhc-icon>                  {# decorative (default) #}
<fqhc-icon name="search" label="Search"></fqhc-icon>    {# icon-only control #}
```

**Why inline SVG and not the Font Awesome classes.** Font Awesome is loaded on
every module page already, so its classes would have been free — but the
components render into Shadow DOM, and document stylesheets do not cross a
shadow boundary. An `<i class="fa fa-user">` inside `fqhc-card`'s shadow root
is an unstyled empty element, and three of the four components that need icons
are in exactly that position. The path data is Font Awesome Free 6.7.2 (solid,
CC BY 4.0), which the project already depends on; each entry records its
upstream name so a glyph can be traced back.

Icons are decorative by default (`aria-hidden`), because every one of them sits
beside text that already carries the meaning — status badges keep their labels.
Pass `label` only for a genuinely icon-only control.

## Dark mode (issue #61)

Every FQHC module surface renders in light or dark. The whole thing is a second
set of **colour tokens** — no component rule is duplicated, because every
component already draws itself from those names.

- **Choosing.** `<fqhc-theme-toggle>` offers System / Light / Dark. System is
  the default and follows `prefers-color-scheme`; an explicit choice is stored
  in `localStorage` under `fqhc-theme` and written to `data-fqhc-theme` on the
  root element. The palette is declared behind *both* the media query and the
  attribute, so the toggle wins in both directions — a dark-OS user can pin
  light and vice versa. It is per-browser rather than per-account on purpose: a
  workstation and an exam-room tablet reasonably want different answers.
- **No flash.** The attribute has to land before the first paint, so it is
  applied by a tiny synchronous snippet in the `<head>` —
  `DesignSystemAssets::themeBootstrapScript()` — ahead of every stylesheet.
  `DarkThemeTest` asserts every module page emits it in that position.
- **Accessible in both.** `ContrastAudit` measures each theme from the values
  its own cascade produces; all 22 pairings clear WCAG 2.1 AA in light and
  dark, asserted in CI and shown side by side in the style guide.
- **Deliberate choices.** Text is softened (`#e2e8f0`, not white) to avoid
  halation; the brand gets *brighter* in dark with near-black text on it;
  elevation reads as a lighter surface rather than a darker shadow, since a
  shadow cannot darken an already-dark ground.
- **Scope.** Module surfaces only. A deep link out to a legacy screen lands on
  that screen's own theme; extending the shell is tracked under #68.

## Component library v2 (issue #62)

The original five elements were enough to build a page of cards holding text —
which is exactly why every workspace home *was* a page of cards holding text.
These are the pieces a dashboard needs instead:

| Element | For |
|---|---|
| `fqhc-stat` | A metric tile: value, label, delta with direction, optional sparkline, optional whole-tile link |
| `fqhc-avatar` | Initials chip, colour derived from the patient id, optional status dot |
| `fqhc-segmented` | Segmented control for today/week/all and similar switches |
| `fqhc-timeline` + `fqhc-timeline-event` | Vertical event list with a time gutter — a visit's arrival → roomed → seen → checked-out |
| `fqhc-skeleton` | Loading placeholder shaped like what is arriving |
| `fqhc-progress` | Linear and ring, for measure rates and completeness |
| `fqhc-toast` | Transient confirmation after an action |

Two details worth knowing:

- **`fqhc-stat` decides the delta colour from `direction`, not the caller.**
  `up`/`down` mean the obvious thing; `up-bad` and `down-good` exist so a
  measure where rising is a regression (open care gaps) cannot be coloured
  green by accident.
- **`fqhc-avatar` derives its hue from the patient id**, at fixed saturation and
  lightness. The point is recognising the same patient across surfaces, which a
  random or sequential colour would defeat.

`ComponentLibraryTest` asserts every element is registered, is demonstrated in
the style guide, extends `FqhcElement` (so it is Shadow-DOM encapsulated), and
that the library hard-codes no colour — the one sanctioned exception being the
avatar's derived hue.

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
composer phpunit-isolated -- --filter 'ColorContrast|ContrastAudit|TokenSheet'
composer phpunit-isolated -- --filter IconRegistry
composer phpunit-isolated -- --filter DarkTheme
composer phpunit-isolated -- --filter ComponentLibrary
```

`DemoDataSetTest` asserts the demo panel actually spans every UDS bucket, payer
category, income band, and special population, keeps data-quality gaps a
minority, and is deterministic.
