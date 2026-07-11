# OpenEMR for FQHCs

This directory documents the goals, principles, and plan for adapting OpenEMR
into a **best-in-class EHR for Federally Qualified Health Centers (FQHCs)** and
look-alikes.

The work has three intertwined objectives that are treated as equally
important:

1. **Compliance** — capture every data element required for HRSA **UDS**
   (Uniform Data System) reporting, and the underlying clinical quality
   measures, sliding-fee, and special-population data that feed it.
2. **Experience** — deliver the look, feel, and performance of a modern,
   web-based SaaS product: a coherent design system, true role-based
   interfaces, and full tablet/mobile/desktop responsiveness.
3. **Certification safety** — do all of the above **without putting the
   ONC-certified core at risk**. The certified surface is a constraint we
   design around, not something we refactor through.

> If you read only one thing, read [`PRINCIPLES.md`](./PRINCIPLES.md) (the
> non-negotiables) and [`ROADMAP.md`](./ROADMAP.md) (what we do next and what
> we deliver long-term).

## Why FQHCs need this

FQHCs operate under requirements that general-practice EHRs do not model well:

- **UDS reporting** to HRSA every calendar year (tables on patients, staffing,
  utilization, clinical quality, costs, and revenue).
- **Sliding Fee Discount Program (SFDP)** driven by household income as a
  percentage of the Federal Poverty Level (FPL).
- **Special populations** tracking (homeless, migrant/seasonal agricultural
  worker, public housing resident, veteran, school-based).
- **Payer mix** that skews heavily Medicaid, uninsured, and grant-funded.
- A workforce spanning providers, nurses, care managers, enrollment/eligibility
  staff, front desk, and behavioral health — each needing a focused interface.

Stock OpenEMR is ONC-certified and already contains much of the clinical and
reporting machinery (the CQM/AMC/CDR engine, demographics, ACL-based roles,
patient portal). It does **not** capture FQHC-specific data elements out of the
box, its UI predates modern responsive design, and its roles are coarse. This
project closes those gaps.

## Document index

| Document | Purpose |
|----------|---------|
| [`PRINCIPLES.md`](./PRINCIPLES.md) | The non-negotiable rules — especially certification safety. Read first. |
| [`ARCHITECTURE.md`](./ARCHITECTURE.md) | How we extend OpenEMR without forking or destabilizing the certified core. |
| [`UDS-REPORTING.md`](./UDS-REPORTING.md) | UDS data elements, where OpenEMR already captures them, and the gaps. |
| [`UDS-DATA-MODEL.md`](./UDS-DATA-MODEL.md) | **The concrete OpenEMR changes for UDS** — field specs, proposed schema, FHIR/UDS+ notes. |
| [`UX-MODERNIZATION.md`](./UX-MODERNIZATION.md) | Design system, role-based interfaces, and responsive strategy. |
| [`ROADMAP.md`](./ROADMAP.md) | Phased plan: immediate next steps and longer-term deliverables. |
| [`PATHWAY-2-ROLE-WORKSPACES.md`](./PATHWAY-2-ROLE-WORKSPACES.md) | **Current path** — the demo-ready role-based experience (Milestone 2). |
| [`STARTER-PATHWAY.md`](./STARTER-PATHWAY.md) | Milestone 1 (complete): essential UDS fields in a modern UI. |
| [`BACKLOG.md`](./BACKLOG.md) | The filed issue set and its mapping. |
| [`reference/`](./reference/) | Source documents (e.g. the CY2026 UDS Proposed PAL). |

## How this maps to GitHub issues

The roadmap is mirrored as one **program epic** linking a small number of
**workstream epics** (certification safety, UDS, design system, role-based UI,
responsive/performance), each with concrete near-term issues. Start from the
[program epic (#2)](https://github.com/Simonparkershames/openemr-fqhc/issues/2)
to navigate.

[`BACKLOG.md`](./BACKLOG.md) is the source text for those issues (and the place
to draft new ones before filing).

## Status

**Milestone 1 complete; Milestone 2 (role-based demo experience) in progress.**

Shipped so far (all additive to the certified core):

- The `oe-module-fqhc` module with design tokens, Web Components, and a
  responsive design-system layer, plus a selectable modern theme
  (`style_fqhc_modern`) for login/finder/new-patient/demographics.
- The **UDS Patient Snapshot** — every essential UDS field visible and
  editable per patient (income/FPL + sliding-fee tier, special populations,
  UDS payer category), backed by side tables and services in `src/FQHC/`.
- **UDS reporting**: Tables 3A/3B/4, ZIP Code, and 5 generated from live data
  with a report UI and cross-table reconciliation; Tables 6B/7 measure map
  and packaging (live CQM wiring still open, #41).
- First role surfaces: the eligibility data-quality worklist and the FQHC
  Workspace home with a live UDS data-health metric.
- Guardrails: scheduled upstream-sync workflow and a certification-impact PR
  checklist.

Current work follows
[`PATHWAY-2-ROLE-WORKSPACES.md`](./PATHWAY-2-ROLE-WORKSPACES.md): per-role
workspaces (front desk, MA, provider, manager), curated role menus, a demo
seed pack, and modern-theme coverage of every screen on the demo path.
