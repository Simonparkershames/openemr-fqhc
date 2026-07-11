# FQHC Roadmap

This roadmap sequences the work. It is intentionally not exhaustive — it makes
clear **what we do next** and **what the longer-term deliverables are**, and it
is mirrored in GitHub issues (one program epic linking workstream epics).

## Phasing principle (revised 2026-07)

The original plan ran compliance-first: foundations → UDS data capture →
experience. Phases 0 and 1 delivered that — and taught us the next lesson:
**the product is judged by its first session, not its report engine.** With
the UDS engine largely built, the experience becomes the front of the queue.
The revised sequencing optimizes for a **demo-ready, role-based product**: an
FQHC evaluator should be able to log in and immediately see a viable
alternative to the big commercial EHRs. Every phase still keeps the
certification suite green.

---

## Phase 0 — Foundations & guardrails ✅ (done)

- Program documentation and issue structure ✅
- Upstream-sync process (scheduled workflow, #9) ✅ and certification-impact
  PR checklist (#19) ✅ — the **required** CI certification gate (#8) remains
  open and worth enabling in parallel.
- `OpenEMR\FQHC` namespace + `oe-module-fqhc` module skeleton ✅
- UDS data-element specs validated against the 2025 manual, CY2026 PAL
  tracked (#11) ✅
- Design-system decision: Twig shells + **Web Components islands**, tokens as
  CSS custom properties (#12) ✅

## Phase 1 — UDS data capture & first reports ✅ (mostly done)

The starter pathway (#13 → #14 #15 #16 #17) and the report build-out:

- Side tables + services for income/FPL, sliding-fee tier, special
  populations, UDS payer classification ✅
- **UDS Patient Snapshot** — see and edit every essential UDS field in a
  modern responsive screen ✅
- Report generation + UI for Tables 3A/3B/4, ZIP, and 5, with cross-table
  reconciliation and a data-quality worklist ✅
- Tables 6B/7 measure map + packaging ✅ — **live CQM population counts still
  open** (#41)
- Patient-level drill-down from report cells still open (#42)

The two open items move to Phase 3 — they are depth, not blockers.

## Phase 2 — The demo-ready role-based experience 👈 (current — Milestone 2)

Make the **whole first session** modern, not just the FQHC menu island. The
plan is [`PATHWAY-2-ROLE-WORKSPACES.md`](./PATHWAY-2-ROLE-WORKSPACES.md),
tracked under epic #6:

1. Role workspace framework + post-login landing (#33)
2. Curated role menus (#34)
3. **Demo practice seed pack** — role accounts, ~50 realistic patients,
   today's schedule (#35)
4. Front-desk workspace (#36)
5. MA/nurse workspace, tablet-first (#37)
6. Provider workspace (#38)
7. Manager/quality workspace (#39)
8. In parallel: modern theme as FQHC default + coverage of the shell,
   calendar, dashboard, messages (#40)

**Deliverable:** four demo roles each run their daily loop end-to-end on
modern (or theme-polished) surfaces from seeded data — the out-of-the-box
demo an FQHC can evaluate in 15 minutes.

## Phase 3 — Depth & polish

- Finish UDS depth: live 6B/7 clinical measures (#41), patient drill-down
  (#42), then the revenue/financial tables.
- Remaining role workspaces: eligibility/enrollment expansion, behavioral
  health (42 CFR Part 2), billing.
- **UDS dashboards** with year-round data-quality worklists.
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
- Each workstream epic lists its near-term issues; the Pathway-2 steps under
  epic #6 are the actionable next steps.
- This file is the human-readable source of truth for sequencing; the issues
  are the unit of execution.
