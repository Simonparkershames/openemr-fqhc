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
