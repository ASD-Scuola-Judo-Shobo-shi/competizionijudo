# Implementation Tracking Checklist

Use this document to track progress on the security, architecture, and performance improvements outlined in `audit.md`.

---

## How to Use

- Mark items as `[ ]` (pending), `[/]` (in progress), or `[x]` (done)
- Add notes or blockers in the `Notes` column
- Commit after completing each phase to track historical progress

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
| 2 | CSRF protection on all forms | [x] | Helpers + validation + token regeneration on login |
| 3 | Fix open redirect & add security headers | [x] | Language controller validated + .htaccess headers |
| 4 | Disable auto-migrations & harden file uploads | [x] | Env check + MIME verification (finfo) + uploads .htaccess |

---

## Phase 2 – High Priority (Before External Testing)

| ID | Task | Status | Notes |
|----|------|--------|-------|
| 5 | Sanitize & validate all inputs | [x] | Superglobal replacement, validators, hardcoded year (partial), layout escaping |
| 6 | Fix race condition in event registration | [x] | Pre-check SELECT + ALREADY_REGISTERED exception pattern |

---

## Phase 3 – Architecture Improvements (Ongoing)

| ID | Task | Status | Notes |
|----|------|--------|-------|
| 7 | Centralize auth & navigation logic | [x] | Session helper class, submenu builder, lazy club loading |
| 8 | Add basic caching & query optimization | [x] | DB indexes (migration), file cache (5min TTL), N+1 fixes |

---

## Phase 4 – Polish & Future-Proofing

| ID | Task | Status | Notes |
|----|------|--------|-------|
| 9 | Pagination & asset optimization | [x] | Admin lists (100/page), club athletes (50/page), caching headers, query profiling |
| 10 | Code quality & testing foundation | [x] | Unit tests + new integration tests (login rate-limit, auth guards). CI runs PHPStan + PHPCS + PHPUnit. |

---

## Remaining Issues (Found after re-audit, not in original tracking)

| ID | Issue | File(s) | Severity | Status |
|----|-------|---------|----------|--------|
| R1 | Hardcoded year 2026 in `calculateJudoCategory()` default param | `src/helpers.php:59` | Low | [x] |
| R2 | Hardcoded year 2026 in `Athlete::eventYearFromDate()` fallback | `src/Model/Athlete.php:64` | Low | [x] |
| R3 | Hardcoded Fiscal Code `CF 92276860928` in language files | `lang/en.php:340`, `lang/it.php:340` | Medium | [x] |
| R4 | Typo in `events.type.only_competitive`: "agonstico" → "agonistico" | `lang/it.php:167` | Low | [x] |
| R5 | Missing feature/integration tests for login, registration, event CRUD | `tests/` | Medium | [x] |
| R6 | Duplicate `/event_details.php` in `build_submenu` paths array | `src/helpers.php:123` | Low | [x] |
| R7 | `session_start()` in `csrf_token()` helper duplicates bootstrap session start | `src/helpers.php:66-68` | Low | [x] |

---

## Milestone Log

| Date | Milestone | Tasks Done |
|------|-----------|------------|
| 2026-06-23 | Audit & tracking docs created | Audit prompt |
| 2026-06-23 | Phase 1 – Critical Security | 1, 2, 3, 4 |
| 2026-06-23 | Phase 2 – High Priority | 5, 6 |
| 2026-06-23 | Phase 3 – Architecture | 7, 8 |
| 2026-06-23 | Phase 4 – Polish (partial) | 9 |
| 2026-06-26 | Phase 4 – Testing foundation | 10 (partial) |

---

## Blockers & Decisions Needed

| Item | Description | Owner | Status |
|------|-------------|-------|--------|
| | | | |
| | | | |

---

*Start this checklist immediately after the approval of the audit. Update and commit after each phase or at the end of each working session.*