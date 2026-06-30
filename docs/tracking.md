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
| C22 | [x] | `ci: gate deployment on quality checks` | R-04, R-08 | `6ee4cc2` | Migration, complete quality, and exact artifact gates precede upload; deploy jobs require successful branch-scoped builds; all actions use verified immutable SHAs; full code checks 111/843; audit not verified |
| C23 | [!] | `ci: verify deployment health` | R-08 |  | Blocked by D03: no approved health/build-revision contract, rollback owner/procedure, or stale-file retirement policy exists |
| C24 | [x] | `fix(routes): add authorized event entry details` | A-01, S-05 | `3f8fa0a` | Canonical route and distinct authorized links/translations agree; real-router anonymous/club/admin tests preserve personal-data scoping; full code checks 113/852; audit not verified |
| C25 | [x] | `chore(routes): remove unsupported public stubs` | A-01, A-08 | `f50f241` | Five export and one public test 404 stubs/claims removed; exact inventory proves every remaining public PHP file has a front-controller, route-wrapper, or redirect role; full code checks 116/874; audit not verified |
| C26 | [x] | `fix(events): persist registration feedback across redirects` | F-02, S-08 | `bb7fda2` | Event-scoped flash survives redirect once and reports added/duplicate/rejected/failed counts; per-item failures are safely logged while partial results remain explicit; full code checks 119/898; audit not verified |
| C27 | [x] | `perf(club): precompute athlete registration counts` | P-01, A-03 | `b9b1fb9` | Controller derives a filtered athlete-count map from its one entry load; template performs no model calls; exactly four prepares at both 1 and 75 athletes; full code checks 121/914; audit not verified |
| C28 | [x] | `refactor(view): remove data access from templates` | A-03, A-07 | `733cdd5` | Prepared layout context owns session/club/config/profiler reads; navigation paths are defined once; templates retain only type annotations/pure formatting enums; club area now uses three constant prepares; full code checks 123/923; audit not verified |
| C29 | [x] | `refactor(performance): remove stale file cache and dead profiler` | A-04, P-04 | `2a91944` | Event/club create-update-delete changes are immediately visible; cache/profiler code is absent; artifact forbids `var/cache` and boots both locales; full code checks 126/938; audit not verified |
| C30 | [x] | `perf(lists): scope and paginate list queries` | P-03, F-01 | `cbde972` | Upcoming lists enforce published/open/current-date lifecycle; public/club lists page at 50; aggregates are identifier-scoped; MySQL 8.4 plans use bounded index scans at representative scale; clean/legacy migrations and full checks pass 129/958 |
| C31 | [x] | `test(domain): define event year category invariants` | A-02, A-07 | `baed051` | Stable age-class keys drive one PHP/JSON weight table; exhaustive locale, event-year, gender, threshold, master, invalid/future, and rendered-client parity checks pass; full checks 133/1462 |
| C32 | [x] | `refactor(domain): derive athlete category for event year` | A-02 | `fb53636` | Athlete rows retain only source facts; open views derive per event/current year; close transition atomically snapshots displayed facts/category; existing closed rows backfill best-effort; MySQL clean/legacy/runtime and full checks pass 136/1488 |
| C33 | [x] | `refactor(core): use the dispatched request consistently` | A-06 | `ea8a841` | Callable/controller routes receive the identical dispatched request; language switching returns safe same-origin responses without globals/exit; rendered 405 responses include `Allow`; full checks pass 142/1507 |
| C34 | [x] | `docs: align supported architecture and operations` | A-07, A-08, F-03, O-01 |  | Dead data/wrappers removed; environment-driven Article 13 notice, upload purge, one-year snapshot expiry, runtime ownership, truthful README, and route inventory verified; hash recorded at C35 startup |
| C35 | [ ] | `test: cover critical application workflows` | Q-03, Q-04 |  | Final full-roadmap verification |

## Current focus

| Field | Value |
|---|---|
| Active commit ID | C35 (next) |
| Objective | Raise the final regression gate across critical HTTP/database workflows and the production artifact |
| Files intentionally in scope | To be selected from the full C35 roadmap section after confirming current critical-path coverage |
| Last targeted test | C34 privacy/upload/retention/delete/route tests pass (19 tests/115 assertions); SQLite retention plan uses `idx_entries_event_club` |
| Last full check | C34 `composer check` passes, including dependency audit (149 tests/1531 assertions); exact production artifact boots Italian/English privacy notices |
| Next action | Commit C34, then mark C35 in progress and inventory the remaining critical workflow coverage before editing |

## Blockers and decisions

| ID | Affects | Owner | Status | Decision/blocker |
|---|---|---|---|---|
| D01 | C05 | Product/operations | Blocking | Confirm the actual production transport (SMTP/API/host sendmail), authentication/TLS requirements, and approved sender identity; the repository and generic FTP-hosting docs do not establish these facts |
| D02 | Deployment | Operations | Open | Repository-side server provisioning and `.env` preservation are documented; assign the named environment owner and approved secure channel before the first live deployment |
| D03 | C23 | Operations | Open | Define stable health URL and rollback procedure for FTP hosting |
| D04 | C25 | Product/privacy | Open | Confirm whether exports are required and, if so, approved formats/fields/access |
| D05 | C32 | User/product | Resolved | Open events use live athlete facts and calculate weight category at consumption; closing atomically snapshots displayed athlete facts plus event-year program/category; closed output uses snapshots; existing closed rows backfill best-effort from current facts |
| D06 | C34 | User/privacy | Resolved | Purge uploads when replaced or their event is deleted; warn before club deletion to export live athlete records; retain closed-event snapshots for one year; source controller/legal-basis/processor notice fields from required production environment values |
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
| 2026-06-29 | C23 | Blocked: health URL/build revision, FTP rollback ownership/procedure, and stale-file retirement around `legacy/` are undefined | Repository/workflow/operations documentation inspection only; no implementation guessed | C24; resume C23 when D03 is resolved |
| 2026-06-29 | C24 | Added the canonical authorized event-entry route, distinct details/entries links and labels, and real-router actor scoping coverage | Focused route/link tests 30/100; exact artifact route manifest/boot pass; full code checks 113/852; dependency audit not verified | C25 |
| 2026-06-29 | C25 | Removed unsupported export/category-test 404 stubs and claims, then enforced an exact intentional public-PHP inventory | Inventory tests 3/22; production artifact rebuild passes; full code checks 116/874; dependency audit not verified | C26 |
| 2026-06-29 | C26 | Added event-scoped one-time flash feedback for added, duplicate, rejected, and failed batch registration outcomes with safe per-item failure logs | Focused feedback/session/repository tests 31/151; full code checks 119/898; dependency audit not verified | C27 |
| 2026-06-29 | C27 | Replaced per-athlete entry reloads with one controller-built filtered registration-count map | Focused tests 13/93; exactly four prepares at 1 and 75 athletes; full code checks 121/914; dependency audit not verified | C28 |
| 2026-06-29 | C28 | Prepared complete layout/authenticated-club context outside templates and centralized navigation route definitions | Focused view/navigation/query tests 24/163; exact artifact boot passes; club area reduced to three prepares at 1/75 athletes; full code checks 123/923; dependency audit not verified | C29 |
| 2026-06-29 | C29 | In progress: removed stale list serialization, dead profiling, and artifact cache storage; verification paused at the environment usage limit | Freshness/layout/query tests 9/51 and cache-free artifact build/manifest pass; artifact boot and full gate not yet rerun | Resume C29 verification; do not commit yet |
| 2026-06-29 | C29 | Completed immediate list freshness and removed cache/profiler runtime and artifact storage after resuming verification | Focused tests 9/51; cache-free manifest and bilingual artifact boot pass; full code checks 126/938; dependency audit not verified | C30 |
| 2026-06-29 | C30 | Added lifecycle-bounded upcoming events, 50-row club/athlete pagination, displayed-ID aggregates, and query-plan-backed list indexes | Focused tests 13/84; MySQL clean/legacy migrations pass; representative plans use indexed page/scope reads; full `composer check` passes 129/958 including audit | C31 |
| 2026-06-29 | C31 | Replaced localized/duplicated category tables with stable shared definitions and exhaustive server/generated-client event-year boundaries | Focused domain/render tests 26/588; full `composer check` passes 133/1462 including audit | C32; evaluate D05 before changing historical behavior |
| 2026-06-29 | C32 | Blocked: event entries reference mutable athlete facts, and no approved historical freeze/backfill policy selects dynamic derivation versus snapshots | Traced athlete writes, entry schema/query joins, registration display, and historical output; no behavior or migration was guessed | D05 decision; C33 remains independently eligible |
| 2026-06-29 | C32 | Completed live event-year category derivation and atomic close-time snapshots under the approved D05 policy | Focused tests 43/718; MySQL clean/legacy migration and runtime immutability checks pass; full `composer check` passes 136/1488 including audit | C33 |
| 2026-06-29 | C33 | In progress: unified dispatched request identity, converted language switching to safe responses, and added rendered 405 handling; paused before full gate at environment usage limit | Focused tests 28/155 plus focused PHPCS/PHPStan pass; required `composer check` not run | Resume C33 verification; do not commit yet |
| 2026-06-30 | C33 | Completed single-request dispatch, response-based safe language switching, and explicit method-not-allowed handling | Focused tests 28/155; full `composer check` passes 142/1507 including audit | C34 |
| 2026-06-30 | C34 | Removed confirmed dead code, aligned supported operations, and implemented the approved configuration-driven privacy and retention controls | Focused tests 19/115 with indexed retention plan; exact artifact excludes uploads and boots configured privacy notices; full `composer check` passes 149/1531 including audit | C35 |

## Milestones

| Milestone | Included commits | Status | Exit condition |
|---|---|---|---|
| M0 Trustworthy gate | C01-C03 | [ ] | Default local quality/test commands are green |
| M1 Active security closed | C04-C14 | [ ] | Critical/high security regression tests pass |
| M2 Releasable artifact | C15-C23 | [ ] | Clean schema and tested artifact can deploy only after quality success |
| M3 Route truth restored | C24-C26 | [ ] | Supported routes/features match UI and README direction |
| M4 Simpler bounded runtime | C27-C30 | [x] | No view queries, stale cache, dead profiler, or unbounded primary lists |
| M5 Domain/core correctness | C31-C35 | [ ] | Event-year behavior and critical workflows are tested end-to-end |

## Completion protocol

When marking a row `[x]`:

1. Add the actual commit hash.
2. Record the targeted test and full-check result in the notes cell or session log.
3. Update “Current focus” to the next eligible row.
4. If implementation invalidates an audit assumption, add a dated note here and update the roadmap before continuing.
5. Do not mark a milestone complete until every non-superseded included row is complete.
