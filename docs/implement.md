# Implementation Guide

Read the `audit.md` to understand the prioritized phases and tasks. Then check `docs/tracking.md` for the current status.

## Current Status

All tasks from **Phase 1, 2, and 3** are fully implemented and verified.

**Phase 4** is in progress:
- **Task 9 (Pagination & Asset Optimization)** – Completed ✅
- **Task 10 (Code Quality & Testing Foundation)** – Partially done. Unit tests exist (HelpersTest, JudoCategoryTest, LocalizationTest, RouterTest). CI pipeline runs PHPStan + PHPCS. Missing integration tests.

## Remaining Issues to Address

| ID | Description | Priority |
|----|-------------|----------|
| R3 | Remove hardcoded Fiscal Code from language files | Medium |
| R5 | Add integration tests for login, registration, event CRUD | Medium |
| R1 | Fix hardcoded year 2026 in `calculateJudoCategory()` | Low |
| R2 | Fix hardcoded year 2026 in `Athlete::eventYearFromDate()` | Low |
| R4 | Fix typo in Italian translation ("agonstico" → "agonistico") | Low |
| R6 | Remove duplicate `/event_details.php` in submenu paths | Low |
| R7 | Fix duplicate `session_start()` in `csrf_token()` helper | Low |

## Approach

1. Address remaining issues starting with Medium priority (R3, R5), then Low (R1, R2, R4, R6, R7).
2. Keep changes minimal and focused per commit.
3. After each change, run `composer check` to ensure no regressions.
4. Update `docs/tracking.md` after completing each item.
5. Use conventional commit format (e.g., `fix(lang): remove hardcoded fiscal code from translations`).