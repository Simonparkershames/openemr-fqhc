# FQHC Roadmap

This roadmap sequences the work. It is intentionally not exhaustive — it makes
clear **what we do next** and **what the longer-term deliverables are**, and it
is mirrored in GitHub issues (one program epic linking workstream epics).

## Phasing principle (revised 2026-09)

The original plan ran compliance-first: foundations → UDS data capture →
experience. Phases 0 and 1 delivered that — and taught us the next lesson:
**the product is judged by its first session, not its report engine.** Phase 2
acted on that by building role workspaces on top of the UDS engine.

The 2026-09 review found the next lesson in turn: **the workspaces work, but
the product still doesn't look designed.** No icons on any surface, no dark
mode, almost no motion, and a five-component library small enough that every
workspace is necessarily a grid of link cards. The expensive foundations —
tokens as CSS custom properties, Web Components, Twig hosting, render tests,
the Dockerless preview toolkit — are all already built, which makes the visual
jump from here unusually cheap. So the design system, and the loop for working
on it, move to the front of the queue.

Every phase still keeps the certification suite green.

---

## Phase 0 — Foundations & guardrails ✅ (done)

- Program documentation and issue structure ✅
- Upstream-sync process (scheduled workflow, #9) ✅ and certification-impact
  PR checklist (#19) ✅
- `OpenEMR\FQHC` namespace + `oe-module-fqhc` module skeleton ✅
- UDS data-element specs validated against the 2025 manual, CY2026 PAL
  tracked (#11) ✅
- Design-system decision: Twig shells + **Web Components islands**, tokens as
  CSS custom properties (#12) ✅

**Carried forward:** CI signal in this fork is not trustworthy — the Inferno
job fails on every PR (#57) and the scheduled matrix is flaky (#58). The
certification gate (#8) can't be meaningful until both are fixed.

## Phase 1 — UDS data capture & first reports ✅ (done, pending merge)

The starter pathway (#13 → #14 #15 #16 #17) and the report build-out:

- Side tables + services for income/FPL, sliding-fee tier, special
  populations, UDS payer classification ✅
- **UDS Patient Snapshot** — every essential UDS field in a modern responsive
  screen ✅
- Report generation + UI for Tables 3A/3B/4, ZIP, and 5, with cross-table
  reconciliation and a data-quality worklist ✅
- Tables 6B/7 measure map and packaging ✅; live CQM counts (#41) and
  patient-level drill-down (#42) are **code complete in PRs #56 and #55**

Remaining UDS work (revenue/financial tables) moves to Phase 5.

## Phase 2 — The demo-ready role-based experience ✅ (pending merge — Milestone M2)

Plan: [`PATHWAY-2-ROLE-WORKSPACES.md`](./PATHWAY-2-ROLE-WORKSPACES.md), epic #6.

1. Role workspace framework + post-login landing (#33) ✅
2. Curated role menus (#34) ✅
3. Demo practice seed pack (#35) ✅
4. Front-desk workspace (#36) ✅
5. MA/nurse workspace, tablet-first (#37) ✅
6. Provider workspace (#38) ✅
7. Manager/quality workspace (#39) — **code complete in PR #54**
8. Modern theme as FQHC default + shell/calendar/dashboard/messages (#40) ✅

**Milestone M2 closes when PRs #54, #55 and #56 land** — which requires the
CI-signal fixes above, because permanent red is what stranded them.

## Phase 3 — Design system v2 & the dev loop 👈 (current — Milestone M3)

Make the product look designed, and make it fast and pleasant to keep it that
way. Tracked under epic #5. Ordered so each step makes the next cheaper:

1. **Living style guide** (#59) — one page showing every token and component
   in every state. Build everything else against it.
2. **Icon system** (#60) — Font Awesome is already loaded on every module page
   and entirely unused. The cheapest available quality win.
3. **Dark mode** (#61) — the token architecture already supports it.
4. **Component library v2** (#62) — stat tile, avatar, segmented control,
   timeline, toast, skeleton, progress. Without these the workspaces cannot
   stop being link grids.
5. **Motion & interaction polish** (#63) — hover, press, focus, busy,
   entrance. Restraint over spectacle; all of it behind one token so
   `prefers-reduced-motion` disables it in one place.
6. **Data table v2** (#64) — sort, sticky header, density toggle, row actions.
7. **Shell polish** (#66) — quiet the legacy chrome as far as SCSS allows.

Supporting: **one-command screenshots + visual regression** (#65) over the
existing `tools/preview/` toolkit — change a token, see every surface in every
theme and breakpoint, catch unintended changes as a diff.

Consuming: **rework the workspace homes into operational dashboards** (#67)
once the v2 components exist.

**Deliverable:** every FQHC surface built from a visible, documented design
system with icons, dark mode, and considered interaction — and a one-command
loop for reviewing any visual change.

## Phase 4 — The FQHC application shell (Milestone M4)

Epic #68. Replace the legacy tab-shell frame for FQHC users: left rail
navigation driven by the existing role menus, global patient search, command
palette, persistent patient/encounter context. Opt-in, reversible, additive —
the certified shell is untouched.

This is the structural half of the shell plan (#66 is the cheap half) and the
single change that stops the product feeling like OpenEMR. It follows Phase 3
deliberately: the shell should be assembled from components that already exist
and are already proven.

## Phase 5 — Depth & the chart

- **The patient chart and encounter note** — the screens a provider lives in,
  still entirely legacy, and the largest remaining experience gap.
- UDS revenue/financial tables (8A, 9D, 9E) — the largest remaining
  compliance gap.
- Remaining role workspaces: eligibility/enrollment expansion, behavioral
  health (42 CFR Part 2), billing.
- Year-round UDS dashboards over the data-quality worklists.
- Patient-portal modernization for mobile-only patients.
- Performance pass across adopted surfaces; i18n coverage for new UI.

---

## Longer-term deliverables (the destination)

- A **certified-safe FQHC distribution** of OpenEMR that tracks upstream and
  passes the certification suite continuously.
- **Complete, auditable UDS reporting** for the patient, clinical-quality,
  utilization, and patient-service-revenue tables, built to each reporting
  year's manual, with drill-down and data-quality tooling.
- A **cohesive design system** and a set of **true role-based workspaces** that
  make OpenEMR feel like a modern SaaS EHR.
- **Full responsiveness** (phone/tablet/desktop) across the daily-driver
  clinical and front-office workflows and the patient portal.
- **Performance budgets** met on common workflows on real clinic hardware.
- WCAG 2.1 AA accessibility and strong multilingual support across new UI.

## Working heuristics (unchanged)

This project is built mostly by **one developer pairing with an AI assistant**.
Picking the next ticket:

- Prefer tickets that are **unblocked**, **small enough to finish in a
  session**, and **independently verifiable** (a test or a screen you can
  click).
- Prefer a thin **vertical slice** (data → service → one screen) over a broad
  horizontal layer — you learn more and ship something usable.
- When two are equal, do the one that **unblocks the most** downstream work.
- Keep the certification suite green at every step; never start a slice you
  can't finish behind a feature flag.
- Responsive, WCAG 2.1 AA, and performance budgets are part of **every**
  screen's definition of done (epic #7), not a separate phase.

When in doubt, ask the assistant to "pick the next ticket" — it can apply
these rules against the open issues and the pathway order.

## How we track progress

- The **program epic** (#2) links the workstream epics and shows phase status.
- Each workstream epic lists its near-term issues, with checkboxes kept in
  sync as slices land.
- **GitHub milestones** map to the phases above: M2 demo-ready role
  workspaces, M3 design system v2 & dev loop, M4 FQHC application shell. A
  milestone's open issues are the current queue.
- This file is the human-readable source of truth for sequencing; the issues
  are the unit of execution.
