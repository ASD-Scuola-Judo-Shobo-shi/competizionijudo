# Implementation Tracking Checklist

Use this document to track progress on the security, architecture, and performance improvements outlined in `audit.md`.

---

## How to Use

- Mark items as `[ ]` (pending), `[/]` (in progress), or `[x]` (done)
- Add notes or blockers in the `Notes` column
- Commit after completing each phase to track historical progress
- **One phase per commit.**

---

## Legend

- `[ ]` — Not started
- `[/]` — In progress
- `[x]` — Completed
- `[~]` — Skipped / deferred
- `[!]` — Blocked / needs decision

---

## Phase 1 – Critical Security (Do First)

| ID | Task | Status | Notes |
|----|------|--------|-------|
| 1 | Lock down authentication & authorization | [x] | Done – secure sessions, rate-limit, token invalidation, entries restriction, edit guard |
| 2 | CSRF protection on all forms | [x] | Helpers + validation + token regeneration |
| 3 | Fix open redirect & add security headers | [x] | Language controller + .htaccess |
| 4 | Disable auto-migrations & harden file uploads | [x] | Env check + MIME verification + uploads .htaccess |

**Phase 1 target:** Before any new feature development.

---

## Phase 2 – High Priority (Before External Testing)

| ID | Task | Status | Notes |
|----|------|--------|-------|
| 5 | Sanitize & validate all inputs | [x] | Superglobal replacement, validators, hardcoded year, layout escaping |
| 6 | Fix race condition in event registration | [x] | Duplicate registration feedback |

**Phase 2 target:** Before first external user trial.

---

## Phase 3 – Architecture Improvements (Ongoing)

| ID | Task | Status | Notes |
|----|------|--------|-------|
| 7 | Centralize auth & navigation logic | [x] | Session helper, submenu component, lazy club loading |
| 8 | Add basic caching & query optimization | [x] | DB indexes, N+1 fixes, file cache, localization cache |

**Phase 3 target:** Ongoing, based on team bandwidth.

---

## Phase 4 – Polish & Future-Proofing

| ID | Task | Status | Notes |
|----|------|--------|-------|
| 9 | Pagination & asset optimization | [x] | Admin lists (100/page), club athletes (50/page), caching headers, query profiling |
| 10 | Code quality & testing foundation | [ ] | Unit tests, CI, PHPStan, PHPCS |

**Phase 4 target:** Before scaling beyond ~500 users.

---

## Milestone Log

| Date | Milestone | Tasks Done |
|------|-----------|------------|
| YYYY-MM-DD | | |
| YYYY-MM-DD | | |
| YYYY-MM-DD | | |

---

## Blockers & Decisions Needed

| Item | Description | Owner | Status |
|------|-------------|-------|--------|
| | | | |
| | | | |

---

*Start this checklist immediately after the approval of the audit. Update and commit after each phase or at the end of each working session.*