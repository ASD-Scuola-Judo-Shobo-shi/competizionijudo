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
| C01 | [x] | `test: isolate PHP session storage` | Q-02 | `0484b1f` | `composer test`: 32 tests/94 assertions; repeat and randomized runs pass without warnings |
| C02 | [x] | `fix(domain): simplify belt and gender presentation` | Q-01, P-02, A-07 | `1fcddb1` | One exhaustive belt definition; locale cache/state preserved; 36 tests/356 assertions |
| C03 | [x] | `fix(view): remove editor artifacts from event form` | Q-04 | `03f4085` | Render test confirms artifact-free output and balanced forms; 37 tests/360 assertions |
| C04 | [x] | `fix(security): stop exposing password reset tokens` | S-01 | `1c97401` | 4 controller tests/21 assertions; full code checks pass with 41 tests/381 assertions; dependency audit not verified |
| C05 | [!] | `feat(auth): deliver password reset links by email` | S-01, A-08 |  | D01: no verified production mail transport or sender requirements; production recovery remains disabled |
| C06 | [x] | `fix(security): enforce athlete ownership on registration` | S-02, S-08 | `5fa7722` | Focused PDO fixture: 5 tests/42 assertions; one constrained write per attempt; full code checks pass with 46 tests/423 assertions; dependency audit not verified |
| C07 | [x] | `fix(security): protect athlete mutations with CSRF` | S-03 | `50d922e` | Controller/application tests: 5 tests/26 assertions; full code checks pass with 51 tests/449 assertions; dependency audit not verified |
| C08 | [x] | `fix(security): move delete actions to CSRF protected posts` | S-03 | `330d664` | Delete-action tests: 6 tests/51 assertions; no destructive GET patterns remain; full code checks pass with 57 tests/500 assertions; dependency audit not verified |
| C09 | [x] | `fix(security): require post for logout` | S-09 | `ec9b6e1` | Logout tests: 5 tests/30 assertions; full code checks pass with 62 tests/530 assertions; dependency audit not verified |
| C10 | [x] | `feat(security): persist authentication throttles` | S-04 | `846ff83` | Focused throttle/migration tests: 15/59; MySQL 8.4 clean and pre-C10 upgrade/repeat checks pass with legacy-compatible SQL mode; full code checks pass with 68 tests/560 assertions; dependency audit not verified |
| C11 | [x] | `fix(auth): consume reset tokens atomically` | S-06, S-08 | `fe972bb` | Focused reset/policy tests: 17/99; locking, conditional claim, reuse/expiry/race-loss, rollback, and all-token invalidation covered; full code checks pass with 80 tests/633 assertions; dependency audit not verified |
| C12 | [x] | `fix(validation): enforce account and event invariants` | S-06 | `8fe0bc5` | Focused validation/no-write tests: 10/58; MySQL 8.4 legacy-compatible clean/upgrade/repeat, duplicate preflight, normalization, and unique rejection pass; full code checks pass with 90 tests/691 assertions; dependency audit not verified |
| C13 | [x] | `fix(events): enforce publication and registration lifecycle` | S-05, F-01 | `dbc7dc7` | Focused lifecycle/access tests: 14/98; MySQL 8.4 boundary checks pass and scoped entry lookup uses the unique index; full code checks pass with 99 tests/747 assertions; dependency audit not verified |
| C14 | [x] | `fix(observability): log failures without exposing internals` | S-07 | `f880815` | Focused logging/redaction tests: 20/111; full code checks pass with 102 tests/785 assertions; dependency audit not verified |
| C15 | [x] | `fix(database): add athlete weight category column` | R-02 | `dd550e9` | Focused migration tests: 3/14; MySQL 8.4 clean, legacy backfill, and repeat checks pass; full code checks pass with 104 tests/797 assertions; dependency audit not verified |
| C16 | [x] | `test(database): verify clean and legacy migrations` | R-02, A-05, Q-03 | `0caa249` | MySQL 8.4 smoke command passes twice; each run verifies clean/legacy paths twice plus tables, columns, unique keys, foreign keys, and backfill; full code checks 104/797; audit not verified |
| C17 | [x] | `fix(database): fail closed on migration errors` | A-05 | `7b7fa12` | Focused runner tests 4/20; C16 clean/legacy/repeat smoke passes under default strict MySQL 8.4 with SQL-mode restoration; full code checks 105/803; audit not verified |
| C18 | [x] | `fix(build): package all runtime assets` | R-01, R-05, R-07 | `8a24717` | Exact 1.2 MB production-only artifact builds and manifest passes with runtime/migration/localization assets, empty writable dirs, and forbidden payload exclusions; full code checks 105/803; audit not verified |
| C19 | [x] | `test(build): boot the deployment artifact` | R-01, Q-03 | `4ab82d8` | Exact production artifact manifest and public routes pass; `/about` boots with HTTP 200 and translated, non-debug output in Italian and English; full code checks 105/803; audit not verified |
| C20 | [x] | `fix(deploy): preserve server environment configuration` | R-03, A-08 | `62f7745` | CI and FTP preserve server-owned `.env`; complete non-secret inventory/provisioning docs added; required production settings fail with redacted actionable logs; full code checks 108/809; audit not verified |
| C21 | [x] | `fix(deploy): stage the repository root htaccess` | R-06 | `1dc0614` | Tracked root `.htaccess` stages byte-for-byte with SHA-256 verification; hidden artifacts are retained; missing/empty content stops build and production upload; full code checks 108/809; audit not verified |
| C22 | [x] | `ci: gate deployment on quality checks` | R-04, R-08 | This commit | Migration, complete quality, and exact artifact gates precede upload; deploy jobs require successful branch-scoped builds; all actions use verified immutable SHAs; full code checks 111/843; audit not verified |
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
| Objective | Evaluate C23 post-deployment health verification against operations decision D03 |
| Files intentionally in scope | None until C23 begins |
| Last targeted test | Workflow gate tests pass (3 tests/34 assertions); MySQL 8.4 clean/legacy migration gate and exact artifact staging/boot pass |
| Last full check | Metadata, syntax, PHPCS, PHPStan, and PHPUnit pass (111 tests/843 assertions); dependency audit not verified because Packagist DNS was unavailable |
| Next action | Confirm whether C23 can proceed without guessing the stable health URL or FTP rollback owner/procedure |

## Blockers and decisions

| ID | Affects | Owner | Status | Decision/blocker |
|---|---|---|---|---|
| D01 | C05 | Product/operations | Blocking | Confirm the actual production transport (SMTP/API/host sendmail), authentication/TLS requirements, and approved sender identity; the repository and generic FTP-hosting docs do not establish these facts |
| D02 | Deployment | Operations | Open | Repository-side server provisioning and `.env` preservation are documented; assign the named environment owner and approved secure channel before the first live deployment |
| D03 | C23 | Operations | Open | Define stable health URL and rollback procedure for FTP hosting |
| D04 | C25 | Product/privacy | Open | Confirm whether exports are required and, if so, approved formats/fields/access |
| D05 | C32 | Product/data owner | Open | Decide whether closed/past entry data is snapshotted or reflects later athlete edits |
| D06 | C34 | Privacy owner | Open | Define upload and athlete-data retention policy |
| D07 | C16, C17 | Code | Resolved | C17 scopes legacy compatibility to applicable known copy statements, restores session mode, and C16 passes under default strict MySQL 8.4 |
| D08 | C14 | User/worktree | Resolved | The concurrent `phpcs.xml` conflict was resolved outside the C14 diff on 2026-06-29 |

Open decisions do not block earlier independent commits. Do not guess when a decision changes user-visible behavior, data retention, credentials, or external delivery.

## Session log

Add one concise row at the end of every working session, including sessions that stop on a blocker.

| Date | Commit ID | Result | Checks | Next step |
|---|---|---|---|---|
| 2026-06-28 | Audit | Created current audit, ordered roadmap, tracker, and continuation prompt | Static trace; command results recorded in Baseline | C01 |
| 2026-06-28 | C01 | Completed isolated PHPUnit session storage and reset regression coverage | `composer test` repeated and randomized: 32 tests/94 assertions; PHPCS passed; full gate retains known C02 PHPStan failure; audit not verified | C02 |
| 2026-06-28 | C02 | Consolidated belt/gender presentation and cached translations per locale without global locale mutation | Metadata, syntax, PHPCS, PHPStan, and PHPUnit pass (36 tests/356 assertions); dependency audit not verified | C03 |
| 2026-06-28 | C03 | Removed literal editor artifacts and added event-form render regression coverage | Metadata, syntax, PHPCS, PHPStan, and PHPUnit pass (37 tests/360 assertions); dependency audit not verified | C04 |
| 2026-06-28 | C04 | Disabled production reset issuance/disclosure and gated local test links behind three explicit flags | Controller tests 4/21; full code checks pass with 41 tests/381 assertions; dependency audit not verified | C05 pending D01 |
| 2026-06-28 | C05 | Blocked: no verified production mail transport or sender configuration is available | Repository/config/deployment scan only; no code or tests changed | C06; resume C05 when D01 is resolved |
| 2026-06-28 | C06 | Replaced the precheck/unconstrained insert with one athlete-and-club-constrained write and explicit duplicate result | Focused PDO fixture 5/42; randomized/full suite 46/423; metadata, syntax, PHPCS, and PHPStan pass; dependency audit not verified | C07 |
| 2026-06-28 | C07 | Enforced CSRF before athlete mutation parsing/database access and replaced helper exit with a rendered 419 response | Controller/application tests 5/26; randomized/full suite 51/449; metadata, syntax, PHPCS, and PHPStan pass; dependency audit not verified | C08 |
| 2026-06-28 | C08 | Replaced club, event, and athlete delete links/GET branches with authenticated CSRF-protected POST actions | Delete-action tests 6/51; no destructive GET patterns; randomized/full suite 57/500; metadata, syntax, PHPCS, and PHPStan pass; dependency audit not verified | C09 |
| 2026-06-28 | C09 | Converted admin/club logout to CSRF-protected POST forms and cleared destroyed session identifiers/cookies | Logout tests 5/30; randomized/full suite 62/530; metadata, syntax, PHPCS, and PHPStan pass; dependency audit not verified | C10 |
| 2026-06-28 | C10 | Added database-backed hashed authentication/reset throttles with bounded expiry cleanup and made DDL migrations tolerate MySQL implicit commits | Focused tests 15/59; MySQL 8.4 clean plus pre-C10 upgrade/repeat checks pass in legacy-compatible mode; full code checks 68/560; dependency audit not verified | C11 |
| 2026-06-28 | C11 | Added transactional one-time reset consumption, invalidated all reset tokens on password changes, and enforced a shared 12-character minimum | Focused tests 17/99; randomized/full suite 80/633; metadata, syntax, PHPCS, and PHPStan pass; dependency audit not verified | C12 |
| 2026-06-28 | C12 | Added explicit club/athlete/event/upload validation, safe mutation errors, normalized email writes, and preflighted unique normalized-email enforcement | Focused tests 10/58; MySQL legacy-compatible clean/upgrade/repeat and duplicate checks pass; full code checks 90/691; dependency audit not verified | C13 |
| 2026-06-28 | C13 | Enforced published public lookup, event-date/deadline lifecycle at read and atomic write boundaries, and session-club entry scoping | Focused tests 14/98; MySQL 8.4 boundary checks and `EXPLAIN` pass; full code checks 99/747; dependency audit not verified | C14 |
| 2026-06-28 | C14 | Implemented correlated redacting JSON logs and generic localized controller/application failures; blocked before commit by concurrent `phpcs.xml` conflict | Focused tests 20/111; isolated metadata/syntax/PHPCS/PHPStan and full PHPUnit 102/785 pass; required `composer check` blocked before audit | Resolve D08, then finish C14 |
| 2026-06-29 | C14 | Completed request-correlated redacting file logs and generic localized controller/application failure handling after D08 resolution | Focused tests 20/111; full code checks 102/785; dependency audit not verified | C15 |
| 2026-06-29 | C15 | Added a schema-aware forward migration for `athletes.weight_category` with conditional legacy backfill and repeat-safe no-op SQL | Focused tests 3/14; MySQL 8.4 clean/legacy/repeat plus athlete write/entry read pass; full code checks 104/797; dependency audit not verified | C16 |
| 2026-06-29 | C16 | Added guarded MySQL clean/legacy migration smoke automation, representative legacy data, relational contract assertions, and CI service coverage | Exact smoke command passed twice with both paths run twice; focused PHPUnit 3/14; full code checks 104/797; dependency audit not verified | C17 |
| 2026-06-29 | C17 | Removed broad unknown-column suppression, added explicit legacy statement applicability, safe versioned failures, and scoped/restored zero-date compatibility | Focused runner tests 4/20; C16 passes under default strict MySQL 8.4; full code checks 105/803; dependency audit not verified | C18 |
| 2026-06-29 | C18 | Made artifact assembly portable and production-only, included language/migration/runtime assets, preserved writable dirs, and enforced required/forbidden manifest checks | Exact 1.2 MB artifact build and independent manifest check pass; full code checks 105/803; dependency audit not verified | C19 |
| 2026-06-29 | C19 | Added public-route manifest checks and production-mode bilingual boot requests against the exact artifact | Manifest and route checks pass; Italian/English `/about` return HTTP 200 with translated content and no debug trace; full code checks 105/803; dependency audit not verified | C20 |
| 2026-06-29 | C20 | Removed generated deployment environments, preserved server-owned secrets, documented first provisioning, and added redacted production startup validation | Configuration tests 3/6; secret-free artifact build and bilingual boot pass; full code checks 108/809; dependency audit not verified | C21 |
| 2026-06-29 | C21 | Staged the tracked root router with hidden-file artifact transfer, checksum verification, and a non-empty pre-upload gate | Exact staging, byte comparison, and SHA-256 checks pass; full code checks 108/809; dependency audit not verified | C22 |
| 2026-06-29 | C22 | Gated deployment artifacts on migration, full quality, and production boot checks; pinned every workflow action to a verified commit SHA | Workflow tests 3/34; MySQL 8.4 migration and exact artifact gates pass; full code checks 111/843; dependency audit not verified | C23 pending D03 review |

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
