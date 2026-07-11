# Pathway 2: The Demo-Ready Role-Based Experience

This is the **current path** for the project (Milestone 2), following the
completed [starter pathway](./STARTER-PATHWAY.md). Tracked under epic
[#6](https://github.com/Simonparkershames/openemr-fqhc/issues/6).

## The north star: the 15-minute demo

An FQHC evaluator — a medical director, an operations manager, an IT lead —
installs this (or opens a hosted demo), logs in with a **role-named demo
account**, and lives entirely in modern surfaces for that role's daily loop:

> Log in as `frontdesk` → land on the front-desk workspace → see today's
> (seeded) schedule → check a patient in.
> Log in as `ma` → rooming worklist → room that patient, enter vitals.
> Log in as `provider` → my day → open the roomed patient → get to the note.
> Log in as `manager` → UDS data health at a glance → run a **populated** UDS
> report → click into the worklist.

That walk **is** the product demo, and it is the argument that an open-source
EMR — maintained in-house or with a small partner, with AI-assisted
development — is a viable alternative to the big commercial builds.

Why this beats continuing UDS-first: the compliance engine is already ~80%
built (see epic #4 status), but the *experience* of the product is still
"legacy OpenEMR with a good menu item." Every future feature inherits whatever
shell we build here, so building the role shell now makes everything after it
cheaper — and demoable.

## What already exists to build on

- The `oe-module-fqhc` module pattern, design tokens, Web Components, and
  responsive table components.
- The **FQHC Workspace home** (live UDS data-health metric + nav cards) — the
  seed of the manager workspace.
- The **eligibility worklist** — the first role surface.
- The `style_fqhc_modern` theme covering login, finder, new-patient,
  demographics.
- Certified ACL groups and the JSON role-menu mechanism
  (`interface/main/tabs/menu/menus/`) — we inherit access control instead of
  inventing it.

## The pathway (ordered)

Each step lands green, is certification-additive, and is demoable on its own.

### Step 1 — Role workspace framework → [#33](https://github.com/Simonparkershames/openemr-fqhc/issues/33)
A workspace registry (role → home page) in the module, role resolution from
the user's ACL group with per-user override, and an opt-in global that makes
the workspace the **post-login landing** instead of Calendar/Messages.

**Visible result:** different users land on different homes after login.

### Step 2 — Curated role menus → [#34](https://github.com/Simonparkershames/openemr-fqhc/issues/34)
JSON menus per FQHC role — ≤ 12 relevant items with the workspace first — so
no role ever faces the full legacy tree.

**Visible result:** the `frontdesk` user's menu fits on one screen and makes
sense.

### Step 3 — Demo practice seed pack → [#35](https://github.com/Simonparkershames/openemr-fqhc/issues/35)
The keystone of "out of the box": demo role accounts, ~50 realistic FQHC
patients (income/FPL, special populations, Medicaid-heavy payer mix), today's
appointments in various states, and this year's encounters — one idempotent
command, never on by default in production.

**Visible result:** a fresh install looks like a living clinic, and the UDS
report has real numbers in it.

### Steps 4–7 — The role workspaces (one per slice)
- **Front desk** → [#36](https://github.com/Simonparkershames/openemr-fqhc/issues/36) — today's schedule, check-in loop, eligibility flags.
- **MA/nurse** → [#37](https://github.com/Simonparkershames/openemr-fqhc/issues/37) — rooming worklist, vitals, screenings due; tablet-first.
- **Provider** → [#38](https://github.com/Simonparkershames/openemr-fqhc/issues/38) — my day, open encounters, results, care gaps (incl. UDS measures).
- **Manager/quality** → [#39](https://github.com/Simonparkershames/openemr-fqhc/issues/39) — consolidates the existing UDS home, report, and worklist, plus a utilization snapshot.

Workspaces may deep-link into theme-polished legacy screens at first; the
workspace is the modern wrapper, and screens get replaced in later slices.

### Step 8 (parallel with 4–7) — Theme coverage → [#40](https://github.com/Simonparkershames/openemr-fqhc/issues/40)
Make `style_fqhc_modern` the FQHC-configuration default and extend it to
every screen the demo path touches: tab shell, calendar, patient dashboard,
messages. The demo loop must never show an unstyled screen.

## Done = Milestone 2

All four demo roles run their loop end-to-end on modern or theme-polished
surfaces, from seeded data, with the certification suite green.

## What comes after (not this pathway)
- **UDS depth:** live 6B/7 clinical measures ([#41](https://github.com/Simonparkershames/openemr-fqhc/issues/41) — needs the seed pack to show meaningful numbers) and patient drill-down from report cells ([#42](https://github.com/Simonparkershames/openemr-fqhc/issues/42) — pairs naturally with the manager workspace).
- Later roles: eligibility/enrollment expansion, behavioral health (42 CFR Part 2 aware), billing.
- Patient-portal responsive pass (epic #7).

## Guardrails (unchanged)
- [#8 CI certification gate](https://github.com/Simonparkershames/openemr-fqhc/issues/8) — still worth enabling in parallel.
- Every step touches the certified core **additively only**
  ([`PRINCIPLES.md`](./PRINCIPLES.md) / [`ARCHITECTURE.md`](./ARCHITECTURE.md)).
- Responsive layout, WCAG 2.1 AA, and a performance budget are part of each
  step's definition of done (epic #7 is a DoD, not a separate queue).
