# Implementation Tracking Checklist

Use this document to track progress on the security, architecture, and performance improvements outlined in `security_audit_and_remediation.md`.

---

## How to Use

- Copy tasks from the main remediation document here as you begin them
- Mark items as `[ ]` (pending), `[/]` (in progress), or `[x]` (done)
- Add notes or blockers in the `Notes` column
- Commit this file with each milestone to track historical progress

---

## Legend

- `[ ]` — Not started
- `[/]` — In progress
- `[x]` — Completed
- `[~]` — Skipped / deferred
- `[!]` — Blocked / needs decision

---

## Phase 1 – Critical Security Fixes

| ID | Task | Status | Notes |
|----|------|--------|-------|
| 1 | Remove auto-migration from production | [ ] | |
| 2 | Implement CSRF protection on all forms | [ ] | |
| 3 | Fix open redirect in language controller | [ ] | |

**Phase 1 target:** Before any new feature development.

---

## Phase 2 – High Severity Issues

| ID | Task | Status | Notes |
|----|------|--------|-------|
| 4 | Add brute-force protection to login endpoints | [ ] | |
| 5 | Standardize session security | [ ] | |
| 6 | Restrict event entries visibility (non-admin) | [ ] | |
| 7 | Harden password reset token management | [ ] | |
| 8 | Add security headers | [ ] | |

**Phase 2 target:** Before first external user trial.

---

## Phase 3 – Medium & Low Priority

| ID | Task | Status | Notes |
|----|------|--------|-------|
| 9 | Replace raw superglobal access with Request object | [ ] | |
| 10 | Add input validation rules | [ ] | |
| 11 | Fix race condition in event registration | [ ] | |
| 12 | Secure file upload handling | [ ] | |
| 13 | Remove unused view parameters | [ ] | |
| 14 | Fix hardcoded year in category calculation | [ ] | |
| 15 | Add authorization guard to edit event route | [ ] | |
| 16 | Sanitize view data in layout | [ ] | |

**Phase 3 target:** Before public launch.

---

## Phase 4 – Architecture Improvements

| ID | Task | Status | Notes |
|----|------|--------|-------|
| A1 | Separate legacy entry points from modern MVC | [ ] | |
| A2 | Introduce middleware pipeline | [ ] | |
| A3 | Replace static model methods with Repository pattern | [ ] | |
| A4 | Centralize session management | [ ] | |
| A5 | Add request validation layer | [ ] | |
| A6 | Replace direct superglobal access with immutable value objects | [ ] | |
| A7 | Adopt component-based view layer | [ ] | |
| A8 | Separate translation/i18n concerns | [ ] | |
| A9 | Introduce event dispatcher / observer pattern | [ ] | |
| A10 | Add structured logging | [ ] | |
| A11 | Move config to typed config object | [ ] | |
| A12 | Implement proper error handling strategy | [ ] | |
| A13 | Add automated tests and CI | [ ] | |
| A14 | Consider adopting modern PHP framework | [ ] | |
| A15 | Establish coding standards and static analysis | [ ] | |

**Phase 4 target:** Ongoing, based on team bandwidth.

---

## Phase 5 – Performance Optimization

| ID | Task | Status | Notes |
|----|------|--------|-------|
| P1 | Add missing database indexes | [ ] | |
| P2 | Eliminate N+1 query patterns | [ ] | |
| P3 | Implement query result caching | [ ] | |
| P4 | Add pagination to list views | [ ] | |
| P5 | Optimize static asset delivery | [ ] | |
| P6 | Reduce per-request bootstrap overhead | [ ] | |
| P7 | Avoid repeated database connections | [ ] | |
| P8 | Add database query profiling (dev) | [ ] | |
| P9 | Lazy-load navigation data | [ ] | |
| P10 | Consolidate redundant queries | [ ] | |

**Phase 5 target:** Before scaling beyond ~500 users.

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

*Start this checklist immediately after the approval of the remediation plan. Update and commit after each completed task or at the end of each working session.*