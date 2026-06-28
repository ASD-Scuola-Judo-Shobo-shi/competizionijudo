# Remediation Tracking

This is the mutable execution record for [roadmap.md](roadmap.md). The audit itself remains the rationale and should not be edited merely to mark progress.

## Status legend

- `[ ]` not started
- `[/]` in progress; only one row should normally have this state
- `[x]` complete and verified
- `[!]` blocked; blocker must be listed below
- `[~]` intentionally superseded/deferred with a recorded decision

## Baseline

| Item | Value |
|---|---|
| Audit revision | `e975cb3` |
| Audit date | 2026-06-28 |
| Branch at audit | `main`, one commit ahead of `origin/main` |
| Working tree at audit start | Clean after `e975cb3` was created |
| `composer check` | Failed: PHPStan `src/Model/Belt.php:207` |
| Default `composer test` | Failed due read-only session path; 1 failure, 30 warnings |
| PHPUnit with `/tmp` session path | Passed: 31 tests, 92 assertions |
| Dependency audit | Not verified: Packagist DNS/network unavailable |
| Deploy build | Failed locally on rsync group preservation; partial artifact missing runtime directories |

## Commit checklist

| ID | Status | Planned commit | Audit IDs | Commit hash | Verification/notes |
|---|---|---|---|---|---|
| C01 | [x] | `test: isolate PHP session storage` | Q-02 | This commit | `composer test`: 32 tests/94 assertions; repeat and randomized runs pass without warnings |
| C02 | [ ] | `fix(domain): simplify belt and gender presentation` | Q-01, P-02, A-07 |  |  |
| C03 | [ ] | `fix(view): remove editor artifacts from event form` | Q-04 |  |  |
| C04 | [ ] | `fix(security): stop exposing password reset tokens` | S-01 |  |  |
| C05 | [ ] | `feat(auth): deliver password reset links by email` | S-01, A-08 |  | Mail transport decision required before start |
| C06 | [ ] | `fix(security): enforce athlete ownership on registration` | S-02, S-08 |  | Requires DB-backed test harness or a focused repository fixture |
| C07 | [ ] | `fix(security): protect athlete mutations with CSRF` | S-03 |  |  |
| C08 | [ ] | `fix(security): move delete actions to CSRF protected posts` | S-03 |  |  |
| C09 | [ ] | `fix(security): require post for logout` | S-09 |  |  |
| C10 | [ ] | `feat(security): persist authentication throttles` | S-04 |  |  |
| C11 | [ ] | `fix(auth): consume reset tokens atomically` | S-06, S-08 |  |  |
| C12 | [ ] | `fix(validation): enforce account and event invariants` | S-06 |  | Preflight duplicate club emails before unique index |
| C13 | [ ] | `fix(events): enforce publication and registration lifecycle` | S-05, F-01 |  |  |
| C14 | [ ] | `fix(observability): log failures without exposing internals` | S-07 |  |  |
| C15 | [ ] | `fix(database): add athlete weight category column` | R-02 |  |  |
| C16 | [ ] | `test(database): verify clean and legacy migrations` | R-02, A-05, Q-03 |  | Requires MySQL in CI/local test environment |
| C17 | [ ] | `fix(database): fail closed on migration errors` | A-05 |  | Depends on C16 |
| C18 | [ ] | `fix(build): package all runtime assets` | R-01, R-05, R-07 |  |  |
| C19 | [ ] | `test(build): boot the deployment artifact` | R-01, Q-03 |  | Depends on C18 |
| C20 | [ ] | `fix(deploy): preserve server environment configuration` | R-03, A-08 |  | Confirm host provisioning procedure |
| C21 | [ ] | `fix(deploy): stage the repository root htaccess` | R-06 |  |  |
| C22 | [ ] | `ci: gate deployment on quality checks` | R-04, R-08 |  | Depends on green C01-C03 gate |
| C23 | [ ] | `ci: verify deployment health` | R-08 |  | Requires stable health URL and rollback owner |
| C24 | [ ] | `fix(routes): add authorized event entry details` | A-01, S-05 |  | Depends on C13 |
| C25 | [ ] | `chore(routes): remove unsupported public stubs` | A-01, A-08 |  | Export reimplementation is a separate product decision |
| C26 | [ ] | `fix(events): persist registration feedback across redirects` | F-02, S-08 |  |  |
| C27 | [ ] | `perf(club): precompute athlete registration counts` | P-01, A-03 |  |  |
| C28 | [ ] | `refactor(view): remove data access from templates` | A-03, A-07 |  |  |
| C29 | [ ] | `refactor(performance): remove stale file cache and dead profiler` | A-04, P-04 |  |  |
| C30 | [ ] | `perf(lists): scope and paginate list queries` | P-03, F-01 |  | Capture representative `EXPLAIN` evidence |
| C31 | [ ] | `test(domain): define event year category invariants` | A-02, A-07 |  |  |
| C32 | [ ] | `refactor(domain): derive athlete category for event year` | A-02 |  | Historical snapshot decision required |
| C33 | [ ] | `refactor(core): use the dispatched request consistently` | A-06 |  |  |
| C34 | [ ] | `docs: align supported architecture and operations` | A-07, A-08, F-03, O-01 |  |  |
| C35 | [ ] | `test: cover critical application workflows` | Q-03, Q-04 |  | Final full-roadmap verification |

## Current focus

| Field | Value |
|---|---|
| Active commit ID | None |
| Objective | Start with C02 |
| Files intentionally in scope | To be recorded when C02 begins |
| Last targeted test | `composer test`: passed twice; randomized PHPUnit run passed (32 tests, 94 assertions) |
| Last full check | `composer check`: metadata, syntax, and PHPCS pass; blocked at the pre-existing C02 PHPStan finding in `Belt.php:207`; dependency audit not verified |
| Next action | Read C02 acceptance criteria and simplify belt/gender presentation |

## Blockers and decisions

| ID | Affects | Owner | Status | Decision/blocker |
|---|---|---|---|---|
| D01 | C05 | Product/operations | Open | Confirm the mail transport supported by production hosting; keep recovery disabled until verified |
| D02 | C20 | Operations | Open | Confirm where production DB/admin secrets are provisioned and who owns `.env` |
| D03 | C23 | Operations | Open | Define stable health URL and rollback procedure for FTP hosting |
| D04 | C25 | Product/privacy | Open | Confirm whether exports are required and, if so, approved formats/fields/access |
| D05 | C32 | Product/data owner | Open | Decide whether closed/past entry data is snapshotted or reflects later athlete edits |
| D06 | C34 | Privacy owner | Open | Define upload and athlete-data retention policy |

Open decisions do not block earlier independent commits. Do not guess when a decision changes user-visible behavior, data retention, credentials, or external delivery.

## Session log

Add one concise row at the end of every working session, including sessions that stop on a blocker.

| Date | Commit ID | Result | Checks | Next step |
|---|---|---|---|---|
| 2026-06-28 | Audit | Created current audit, ordered roadmap, tracker, and continuation prompt | Static trace; command results recorded in Baseline | C01 |
| 2026-06-28 | C01 | Completed isolated PHPUnit session storage and reset regression coverage | `composer test` repeated and randomized: 32 tests/94 assertions; PHPCS passed; full gate retains known C02 PHPStan failure; audit not verified | C02 |

## Milestones

| Milestone | Included commits | Status | Exit condition |
|---|---|---|---|
| M0 Trustworthy gate | C01-C03 | [ ] | Default local quality/test commands are green |
| M1 Active security closed | C04-C14 | [ ] | Critical/high security regression tests pass |
| M2 Releasable artifact | C15-C23 | [ ] | Clean schema and tested artifact can deploy only after quality success |
| M3 Route truth restored | C24-C26 | [ ] | Supported routes/features match UI and README direction |
| M4 Simpler bounded runtime | C27-C30 | [ ] | No view queries, stale cache, dead profiler, or unbounded primary lists |
| M5 Domain/core correctness | C31-C35 | [ ] | Event-year behavior and critical workflows are tested end-to-end |

## Completion protocol

When marking a row `[x]`:

1. Add the actual commit hash.
2. Record the targeted test and full-check result in the notes cell or session log.
3. Update “Current focus” to the next eligible row.
4. If implementation invalidates an audit assumption, add a dated note here and update the roadmap before continuing.
5. Do not mark a milestone complete until every non-superseded included row is complete.
