# poisk-turov-test — Autopilot State

Updated: 2026-08-30 11:12 +02:00

Operational companion to `AGENTS.md`. `AUTOPILOT_STATE.json` is the machine-readable resume point, `ARCHITECTURE.md` remains the canonical architecture source of truth, `TEST_MATRIX.md` owns CI/test policy, and `PRODUCT_ROADMAP.md` owns the broader product roadmap.

## Current phase — DESIGN SYSTEM 1.0 / SITE-WIDE VISUAL UNIFICATION

The owner's current explicit direction makes **AnyTour Design System 1.0 and whole-site visual unification Priority #1**. The mature tour-search flow is the quality reference, not the score of the entire public site.

Priority order:

`production broken → lead loss → incorrect data → broken user journey → Design System/site-wide visual coherence → supporting technical refactor → content/SEO → cosmetic cleanup`

## Current production baseline

- PRs #325/#326 established shared editorial rhythm and canonical content-gap/readable-measure tokens.
- PR #330 is production-green: homepage and shell-based editorial pages share one final layer for section heading/copy geometry, card/surface treatment and primary/secondary action geometry.
- PR #332 is production-green: the shared 375/430 community/footer block no longer wastes five full-width rows. Social links and app-store badges use a compact safe grid while preserving verified destinations and 44px+ interaction targets.
- Production root/search visual evidence is green at **375 / 430 / 768 / 1024 / 1440** after #332, with no horizontal overflow or duplicate footer. The 375px production screenshot confirms MAX + Telegram side by side, VK on the next row and App Store + Google Play side by side.
- Production tourist journey is green after the footer release; lead-writing behavior remains untouched.
- Whole-site coherence moves cautiously from the owner's **6.5/10 baseline to ~6.8/10** based on direct production evidence. Search remains materially stronger (~8.75 reference).

## Exact next work order

1. **Shared header/navigation audit.** Compare `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages at 375/430/768/1024/1440. Fix confirmed active-state, wrapping, phone/menu geometry or spacing drift.
2. Keep `/poisk-turov/` on its mature search shell; use the compatibility layer for safe visual alignment rather than attempting the deferred full component swap.
3. Re-audit representative `/country/` pages and `/hot/` against homepage/search hierarchy, cards, breadcrumbs and CTA language.
4. Continue moving confirmed page-specific drift into shared typography/grid/section/card/button/breadcrumb primitives.
5. Validate the full visual journey: homepage → destination/hot → search → results → selected tour → lead.
6. Use technical refactor slices only where they remove a concrete Design System blocker or after the visual coherence gate is materially stronger.

## Mandatory protections

Do not modify without explicit approval:

- Yandex Metrika configuration, goals or events;
- analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- server/platform architecture outside the allowed repository/deploy scope.

Preserve verified social/app destinations. Do not redesign or replace the AnyTour logo. Legal/payment migration and PR #254 remain deferred. Full `/poisk-turov/` shared-header component replacement remains deferred until an atomic swap has equivalent browser coverage.

## Execution policy

Work through multiple independent SAFE visual/UX tasks per session while time allows. Fix confirmed spacing, wrapping, overflow, duplicate-shell, hierarchy and responsive problems before decorative flourishes. For each user-facing slice, run focused checks, preserve mature search/lead regressions, validate 375/430/768/1024/1440, deploy only after relevant checks are green, and inspect post-deploy live evidence before treating the slice as DONE.
