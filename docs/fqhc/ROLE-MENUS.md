# Curated role-based menus

**Issue #34 · Pathway 2, Step 2 · Parent epic #6**

Stock OpenEMR shows every user the same ~hundreds-of-items menu tree. Nothing
reads as "dated" faster, and nothing hides the good screens better. FQHC staff
need the 8–12 items their role actually uses — and the role's workspace home as
the first thing they see.

This ships one curated menu per FQHC role. They are **additive**: new JSON files
alongside the stock menus, using OpenEMR's existing, certified menu mechanism.
No certified menu file is modified.

## How OpenEMR picks a user's menu

`MainMenuRole::getMenu()` reads `users.main_menu_role` for the logged-in user
and loads the matching menu definition:

- A bare value (e.g. `fqhc_provider`) loads
  `interface/main/tabs/menu/menus/<value>.json` — the mechanism these files use.
- A value ending in `.json` loads a site-local custom menu from
  `sites/<site>/documents/custom_menus/`.
- Empty falls back to `standard`.

After loading, the menu passes through the same ACL and global-setting
restriction pass as every other menu, so each item still only appears for users
whose ACLs permit it — the curation narrows *what is offered*, never *what is
authorized*.

## The menus

| File | Role | First item → then daily drivers |
|------|------|--------------------------------|
| `fqhc_front_desk.json` | Front desk | Home · Calendar · Check-in · Finder · Register Patient · Eligibility Worklist · Messages · Recalls |
| `fqhc_ma.json` | MA / nurse | Home · Rooming · Calendar · Finder · Patient Dashboard · Current Visit · Create Visit · Lab Overview · Messages |
| `fqhc_provider.json` | Provider | Home · Calendar · Flow · Finder · Patient Dashboard · Current Visit · Fee Sheet · Pending Review · Patient Results · Messages |
| `fqhc_eligibility.json` | Eligibility / enrollment | Home · Eligibility Worklist · Finder · Register Patient · Patient Dashboard · Eligibility 270/271 · UDS Report · Messages |
| `fqhc_billing.json` | Billing | Home · Billing Manager · Fee Sheet · Checkout · Posting/Batch Payments · Claim Tracker · EDI History · Finder |
| `fqhc_manager.json` | Manager / quality | Home · UDS Report · Eligibility Worklist · Calendar · Finder · Clinical Measures · Appointments/Encounters · Patient List · Messages |
| `fqhc_admin.json` | Admin | Home · Config · Users · ACL · Facilities · Forms Admin · Lists · Modules · UDS Report · Finder |

Each menu keeps ≤ 12 top-level items, leads with **Home** (the role's FQHC
workspace), and pushes overflow into a small **More** group.

### Home item and the FQHC module

Every menu's first item uses `menu_id: "fqhc0"` and points at the FQHC module
home. That id is intentional: the module's menu subscriber
(`oe-module-fqhc` `Bootstrap::addMenuItem`) skips adding its own top-level FQHC
entry when an item with that id is already present, so the curated menu leads
with Home instead of getting a duplicate FQHC entry appended at the end. Menus
that a role needs still list **UDS Report** and **Eligibility Worklist**
explicitly.

> When the per-role workspace homes land (issue #33), repoint each menu's Home
> `url` from the module index at the role-specific workspace. The rest of each
> menu is unaffected.

## Assigning a menu to a user

### In the admin UI
**Administration → Users → (edit user) → Main Menu Role.**

The built-in selector lists `Standard`, `Answering Service`, and `Front Office`.
The curated FQHC menus are additive files that the stock selector does not
enumerate, so set them one of these ways:

- Add the basename to the selector list your site uses, or
- Set the column directly (below). The value is the filename **without** the
  `.json` suffix.

### By SQL (or from the demo seed)

```sql
UPDATE users SET main_menu_role = 'fqhc_front_desk'  WHERE username = 'frontdesk';
UPDATE users SET main_menu_role = 'fqhc_eligibility' WHERE username = 'eligibility';
UPDATE users SET main_menu_role = 'fqhc_ma'          WHERE username = 'ma';
UPDATE users SET main_menu_role = 'fqhc_provider'    WHERE username IN ('provider', 'provider2');
UPDATE users SET main_menu_role = 'fqhc_billing'     WHERE username = 'billing';
UPDATE users SET main_menu_role = 'fqhc_manager'     WHERE username = 'manager';
```

These usernames are the demo staff accounts created by the demo seed pack
(issue #35). Seeding those accounts and assigning their menus is what makes each
demo role log into a short, task-relevant menu with its workspace home first.

## Adding or editing items

Each item is an object with `label`, `menu_id`, `target` (the tab identity),
`url`, `children`, `requirement` (0 = always considered), and an optional
`acl_req` / `global_req_strict`. Reuse the `acl_req` from the matching item in
`standard.json` so an item never appears for a user who lacks its ACL. Group
items omit `target`/`url` and carry a `children` array. Keep each role at
≤ 12 top-level items — the point is focus.
