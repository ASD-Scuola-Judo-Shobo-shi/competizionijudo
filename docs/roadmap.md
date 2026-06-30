# Remediation Roadmap

This roadmap converts [audit.md](audit.md) into a sequence of deliberately small commits. Execute in order unless a documented blocker requires a change. Each security or data fix includes its regression test in the same commit; do not create a separate “tests later” gap.

## Working rules

- Start every session by reading `audit.md`, this file, and [tracking.md](tracking.md), then inspect `git status` and recent commits.
- Work on one commit ID at a time. Do not mix opportunistic cleanup into a security or schema fix.
- Preserve unrelated user changes and ignored local files.
- Treat existing migrations as applied. Use new forward migrations; do not make production depend on editing an old migration.
- A task is complete only when its acceptance checks pass and its row in `tracking.md` contains the commit hash and evidence.
- Run targeted tests while iterating. Before a commit, run `composer check`; for build/database commits also run their listed smoke checks.
- If dependency audit cannot access Packagist, record it as “not verified”; do not claim it passed.
- Never expose real credentials, reset tokens, personal data, or `.env` contents in logs, test fixtures, commits, or documentation.

## Phase 0 — Restore a trustworthy local gate

### C01 — Isolate PHPUnit session storage

Commit: `test: isolate PHP session storage`

Changes:

- Configure tests to use a writable temporary session directory before `src/bootstrap.php` starts a session.
- Reset session/global state deterministically between tests.
- Add an assertion that repeated test runs do not leak login-attempt state.

Acceptance:

- `composer test` passes without `-d session.save_path=...`, warnings, or order dependence.

Resolves: `Q-02`.

### C02 — Simplify belt/gender localization and restore PHPStan

Commit: `fix(domain): simplify belt and gender presentation`

Changes:

- Replace the non-exhaustive guarded `match` in `Belt::components()` with one exhaustive source of component data.
- Remove redundant belt color/text/split representations that are not used.
- Stop changing the global locale for every belt/gender label; translate in the active locale or add a per-locale cache that does not clear existing messages.
- Add focused enum tests for all cases in both locales.

Acceptance:

- `composer analyse`, `composer test`, and `composer check` pass (dependency audit permitting network access).

Resolves: `Q-01`, `P-02`, part of `A-07`.

### C03 — Remove accidental template output and cover it

Commit: `fix(view): remove editor artifacts from event form`

Changes:

- Delete the literal parameter/tool tags at the end of `views/admin/add_event.php`.
- Add a render test asserting those strings are absent and the form closes correctly.

Acceptance:

- Targeted render test and `composer check` pass.

Resolves: `Q-04` symptom.

## Phase 1 — Close active security vulnerabilities

### C04 — Stop password-reset token disclosure and enumeration

Commit: `fix(security): stop exposing password reset tokens`

Changes:

- Never include a reset token/link in a production response.
- Permit a displayed link only when `APP_ENV=local`, `APP_DEBUG=true`, and an explicit test-only flag is enabled.
- Return the same status, body shape, and practical timing for known and unknown emails.
- Add controller tests for production, local-test, known, and unknown cases.
- Until C05 lands, make the production response explicit that recovery delivery is unavailable without revealing whether the account exists.

Acceptance:

- No production response contains `token=`.
- Known and unknown account tests receive the same generic response.

Resolves the exploitable part of `S-01`.

### C05 — Add real reset-link delivery

Commit: `feat(auth): deliver password reset links by email`

Changes:

- Add a small injectable reset-link notifier using the hosting-supported mail transport.
- Add required mail settings to `.env.example`; fail closed when transport is unconfigured.
- Send only to the club's recovery address; never log the raw token.
- Use a fake notifier in tests and verify exactly one delivery for a known account.

Acceptance:

- Production recovery remains disabled when mail is unconfigured.
- Configured integration test proves delivery without rendering or logging the token.

Completes: `S-01`; supports `A-08`.

### C06 — Enforce club ownership during event registration

Commit: `fix(security): enforce athlete ownership on registration`

Changes:

- Make the registration write conditional on `athletes.id` and `athletes.club_id` in one repository operation.
- Reject a foreign/missing athlete without inserting an entry.
- Translate duplicate-key violations into the existing domain result; do not rely on a race-prone SELECT as authority.
- Add database-backed tests for own athlete, foreign athlete, missing athlete, and duplicate registration.

Acceptance:

- A forged athlete ID cannot create an entry.
- The database unique constraint remains present and duplicates are handled predictably.

Resolves: `S-02`, part of `S-08`.

### C07 — Protect athlete add/edit with validation and CSRF

Commit: `fix(security): protect athlete mutations with CSRF`

Changes:

- Validate CSRF before reading athlete mutation fields.
- Return a controlled 419 response instead of calling `exit` from the helper.
- Add rejection tests for missing/invalid tokens and acceptance tests for valid tokens.

Acceptance:

- No athlete insert/update occurs after invalid CSRF.

Resolves the POST portion of `S-03`.

### C08 — Replace destructive GETs with POST actions

Commit: `fix(security): move delete actions to CSRF protected posts`

Changes:

- Add explicit POST routes/actions for club, event, and athlete deletion.
- Replace delete anchors with small forms containing CSRF tokens.
- Remove all `?delete=` behavior from GET handlers.
- Test GET as non-mutating, invalid CSRF as rejected, ownership/admin checks, and valid deletion.

Acceptance:

- `rg '\?delete=|query\(.delete' src views routes` finds no destructive flow.
- Cascading deletes require authenticated POST plus a valid token.

Completes: `S-03`.

### C09 — Make logout a POST action

Commit: `fix(security): require post for logout`

Changes:

- Replace admin and club logout links/routes with POST forms and CSRF validation.
- Regenerate/clear the session cookie on logout.

Acceptance:

- GET logout routes do not mutate sessions; POST tests pass.

Resolves: part of `S-09`.

### C10 — Add persistent authentication and reset throttling

Commit: `feat(security): persist authentication throttles`

Changes:

- Add a forward migration for bounded throttle records.
- Introduce one small throttle component keyed by hashed normalized account plus trusted network signal.
- Apply it to admin login, club login, and forgot-password requests.
- Add expiry cleanup and deterministic clock-driven tests.

Acceptance:

- A new browser session does not reset the limit.
- Raw emails/IPs are not stored in throttle keys or emitted to logs.

Resolves: `S-04`.

### C11 — Make reset consumption atomic and enforce password policy

Commit: `fix(auth): consume reset tokens atomically`

Changes:

- Claim a valid, unused, unexpired token conditionally inside a transaction with the password update.
- Enforce a documented minimum password policy for registration, admin club edits, and reset.
- Invalidate all outstanding tokens after a successful password change.
- Add concurrency-oriented repository tests where practical.

Acceptance:

- A token succeeds once; expired, reused, and concurrent second uses fail.

Resolves: reset portion of `S-06` and `S-08`.

### C12 — Add server-side input and account-integrity validation

Commit: `fix(validation): enforce account and event invariants`

Changes:

- Add focused validators for club, athlete, and event commands; avoid a generic validation framework.
- Validate enum membership, dates, deadline ordering, positive weight, required federal code, email formats, and upload size.
- Normalize club emails and add a duplicate-data preflight plus a new unique index migration.
- Replace database exception text with safe field errors.

Acceptance:

- Invalid direct POSTs are rejected before SQL/file writes.
- Duplicate normalized emails cannot be created.

Resolves: remaining `S-06`.

### C13 — Enforce event visibility and lifecycle

Commit: `fix(events): enforce publication and registration lifecycle`

Changes:

- Public event lookup must require `published=1`.
- Registration must require published, open, not past deadline, and an allowed event date/lifecycle.
- Fix the latent non-admin entry filter so a club can only see its own detailed rows.
- Add boundary tests for unpublished, closed, deadline-equal, deadline-past, admin, and club access.

Acceptance:

- Unpublished data is not returned by public ID lookup.
- Club entry queries are always scoped to the session club.

Resolves: `S-05`, part of `F-01`.

### C14 — Add safe error logging

Commit: `fix(observability): log failures without exposing internals`

Changes:

- Add a minimal structured file logger under `var/log` with correlation ID and safe context.
- Log uncaught/controller failures server-side; return localized generic errors.
- Never log passwords, session IDs, reset tokens, full personal records, or database credentials.

Acceptance:

- Failure-path tests see generic UI text and a redacted log record.

Resolves: `S-07`.

## Phase 2 — Make schema and releases reliable

### C15 — Add the missing athlete schema column

Commit: `fix(database): add athlete weight category column`

Changes:

- Add a new forward-only migration that safely adds `athletes.weight_category` for existing and clean databases.
- Do not depend on modifying the already-applied baseline migration.
- Document any backfill/default decision.

Acceptance:

- Athlete add/update and entry queries work after upgrading an existing schema.

Resolves: `R-02` immediate defect.

### C16 — Add clean and legacy migration smoke tests

Commit: `test(database): verify clean and legacy migrations`

Changes:

- Add a MySQL CI service and a script/test that runs all migrations on an empty database.
- Add a minimal representative legacy schema fixture and upgrade it.
- Assert required tables, columns, unique keys, and foreign keys.

Acceptance:

- Both schema paths pass twice without hidden partial state.

Supports: `R-02`, `A-05`, `Q-03`.

### C17 — Make migration execution fail closed

Commit: `fix(database): fail closed on migration errors`

Changes:

- Remove broad unknown-column suppression.
- Make legacy-copy behavior explicitly schema-aware or mark it inapplicable without masking unrelated failures.
- Record a migration only after every required operation succeeds.
- Make the migration command report the exact failed version without leaking credentials.

Acceptance:

- An injected failing statement leaves its migration pending.
- C16 remains green.

Resolves: `A-05`.

### C18 — Package the complete runtime artifact

Commit: `fix(build): package all runtime assets`

Changes:

- Include `lang/`, `migrations/`, and the migration runner script.
- Exclude tests, docs, local caches, backups (`*.bak`, editor files), and runtime upload contents unless explicitly seeded.
- Use portable rsync ownership/group options.
- Keep writable empty runtime directories.

Acceptance:

- Build succeeds on the local managed filesystem and CI.
- Manifest assertions prove required paths exist and forbidden paths do not.

Resolves: `R-01`, `R-05`, `R-07`.

### C19 — Boot-test the built artifact

Commit: `test(build): boot the deployment artifact`

Changes:

- Start the artifact with PHP's built-in server in production mode and request a database-free page in both locales.
- Assert HTTP 200, translated content, and no debug trace.
- Add route-manifest checks for expected public endpoints.

Acceptance:

- The smoke test fails if translations, autoload files, views, or routes are missing.

Resolves artifact portion of `Q-03` and prevents `R-01` regression.

### C20 — Preserve server-owned secrets and complete environment docs

Commit: `fix(deploy): preserve server environment configuration`

Changes:

- Stop generating/uploading a partial `.env` in the public deployment artifact.
- Explicitly exclude `.env` from FTP synchronization and document secure first-time provisioning.
- Expand `.env.example` with non-secret placeholders for every consumed setting; remove or implement unused settings.
- Add a startup configuration check for required production values.

Acceptance:

- Artifact inspection contains no `.env` or secret values.
- Missing required production configuration fails with a safe actionable server log.

Resolves: `R-03`, part of `A-08`.

### C21 — Stage the correct root router

Commit: `fix(deploy): stage the repository root htaccess`

Changes:

- Copy the actual root `.htaccess` or rename it consistently.
- Make the workflow fail rather than warn when the routing artifact is required and absent.
- Verify its checksum/content in the artifact job.

Acceptance:

- Root artifact contains `.htaccess` and the production job cannot upload an empty directory.

Resolves: `R-06`.

### C22 — Gate deployment on the full quality suite

Commit: `ci: gate deployment on quality checks`

Changes:

- Make deployment consume a successful quality/build result, or run the complete gate in the deploy workflow.
- Pin third-party actions to reviewed commit SHAs, with update notes.
- Prevent production deployment from pull requests and failed/cancelled CI runs.

Acceptance:

- A deliberately failing test/static check prevents all deploy jobs.

Resolves: `R-04`; hardens `R-08`.

### C23 — Add post-deploy health verification

Commit: `ci: verify deployment health`

Changes:

- Request a stable health endpoint after upload and validate status/build revision.
- Emit a clear failed deployment signal and document the manual rollback procedure compatible with the FTP host.
- Decide how stale remote files are retired without touching the independent `legacy/` directory.

Acceptance:

- A broken deployment is marked failed before the workflow reports success.

Resolves: `R-08`.

## Phase 3 — Repair routes and feature truth

### C24 — Expose entry details under an authorized canonical route

Commit: `fix(routes): add authorized event entry details`

Changes:

- Add one canonical route/URL for `EventController::entries()` after C13 authorization is in place.
- Update links and translations so “details” and “entries” mean distinct things.
- Add admin/club/anonymous route tests and personal-data scoping assertions.

Acceptance:

- The route inventory, links, and controller behavior agree.

Resolves entry portion of `A-01`.

### C25 — Remove unsupported export/test endpoints

Commit: `chore(routes): remove unsupported public stubs`

Changes:

- Remove the five export stubs and public category-test stub that only return router 404s.
- Remove the export claim from README for now.
- Record exports as a separate product feature only if a concrete format/access requirement is approved; do not serve CSV with a misleading Excel extension.

Acceptance:

- No tracked public PHP file lacks an intentional route/redirect/static role.

Resolves export/test portion of `A-01`, part of `A-08`.

### C26 — Preserve duplicate-registration feedback

Commit: `fix(events): persist registration feedback across redirects`

Changes:

- Add one-time flash messages or render a safe result after POST/redirect/GET.
- Report added, already registered, rejected, and failed counts without partial ambiguity.

Acceptance:

- Duplicate registration produces visible feedback after redirect.

Resolves: `F-02`, remaining batch clarity in `S-08`.

## Phase 4 — Remove avoidable complexity and query waste

### C27 — Eliminate the club-area N+1 query

Commit: `perf(club): precompute athlete registration counts`

Changes:

- Query grouped counts once or build a map once from controller-loaded entries.
- Remove `Entry::findByClub()` and all model calls from the view loop.
- Add a query-count or repository-call regression assertion.

Acceptance:

- Query count is constant as athlete count grows.

Resolves: `P-01`, part of `A-03`.

### C28 — Pass complete layout/view data from controllers

Commit: `refactor(view): remove data access from templates`

Changes:

- Prepare authenticated club/navigation context before rendering.
- Remove session/model queries from `views/layouts/app.php`.
- Consolidate duplicated navigation path definitions.

Acceptance:

- `rg 'Model\\|Database::|Session::' views` contains only intentional formatting enum references, then remove those where practical.

Resolves: remaining `A-03`, part of `A-07`.

### C29 — Remove premature file caching and dead profiler

Commit: `refactor(performance): remove stale file cache and dead profiler`

Changes:

- Remove list caching, `Cache` bootstrap initialization/class if no longer used, and profiler code that is never populated.
- Keep the supporting database indexes that query plans justify.
- Add basic request timing/logging only through the safe logger if operationally useful.

Acceptance:

- Create/update/delete results are immediately visible.
- No serialized application objects are written to `var/cache`.

Resolves: `A-04`, `P-04`.

### C30 — Bound event, club, and admin list queries

Commit: `perf(lists): scope and paginate list queries`

Changes:

- Filter upcoming events by date/lifecycle with an explicit product rule.
- Limit admin entry counts to displayed event IDs.
- Paginate club list and club overview consistently.
- Validate with `EXPLAIN` against representative data; remove only demonstrably redundant indexes in a new migration.

Acceptance:

- Result size and query work are bounded per page.

Resolves: `P-03`, remaining `F-01`.

## Phase 5 — Correct domain calculations and core flow

### C31 — Centralize category definitions and event-year tests

Commit: `test(domain): define event year category invariants`

Changes:

- Add boundary tests for every age class, gender, weight threshold, master case, invalid/future birth date, and event year.
- Generate client-side definitions from the same PHP source used by server calculation.
- Remove duplicate weight tables.

Acceptance:

- PHP result and generated client definition tests cannot drift.

Supports: `A-02`, part of `A-07`.

### C32 — Derive categories in event context

Commit: `refactor(domain): derive athlete category for event year`

Changes:

- Store athlete source facts, not a category tied to the edit year.
- Calculate program/weight category with the selected event date during registration and entry display.
- Decide and implement explicit snapshots when registrations become historically immutable; migrate/backfill only after the rule is tested.

Acceptance:

- The same athlete can be categorized correctly for events in different years.
- Historical-output behavior after athlete edits is documented and tested.

Resolves: `A-02`.

### C33 — Unify request/response routing

Commit: `refactor(core): use the dispatched request consistently`

Changes:

- Remove the request stored in `Router`; inject the request passed to `dispatch()`.
- Convert language switching to a normal controller response using `Request` rather than superglobals/`exit`.
- Add method-not-allowed behavior and route tests.

Acceptance:

- Controller and callable routes receive the same request object; no controller emits headers or exits directly.

Resolves: `A-06`.

### C34 — Remove confirmed dead code and align documentation

Commit: `docs: align supported architecture and operations`

Changes:

- Remove unused `Competition` data, unused view/controller helpers, obsolete wrappers, and stale comments only after usage/route tests confirm they are dead.
- Update README requirements, quick start, feature list, security claims, migration procedure, deployment procedure, and links to root audit/roadmap/tracking/prompt documents.
- Document runtime upload ownership and retention; exclude runtime uploads from code deployments.

Acceptance:

- A clean checkout can follow README to a working local setup.
- Every advertised feature has a route-level test.

Resolves: `A-07`, `A-08`, `F-03`, `O-01`.

### C35 — Raise the final regression gate

Commit: `test: cover critical application workflows`

Changes:

- Add HTTP/database tests for login, reset, club registration, athlete CRUD, event CRUD, registration ownership, entries privacy, migration, and built artifact.
- Expand PHPCS to routes/config/views where useful; add template smoke rendering rather than forcing unsuitable static rules.
- Add a dependency-audit policy and a modest changed-code coverage threshold.

Acceptance:

- `composer ci` passes from a clean checkout with the MySQL test service.
- The dependency audit result is recorded and no critical path relies only on browser validation.

Resolves: `Q-03`, `Q-04`; verifies the complete roadmap.

## Final decisions and future scope

- **Exports:** C25 removes false/broken endpoints; exports are not part of the supported product. A future feature requires approved columns, encoding, format, authorization, and handling of minors' personal data.
- **Historical entry snapshots:** C32 resolves the rule: open events derive live facts for the event year; close atomically snapshots displayed facts and categories; closed output uses snapshots.
- **Mail transport:** C05 uses a provider-neutral mailer boundary with the Aruba Linux Basic PHP-mail adapter. Production activation requires the documented control-panel and synthetic delivery test.
- **Privacy operations:** C34 implements the approved upload and one-year snapshot retention behavior. The Aruba account administrator owns truthful notice values, host log/backup expiry, rights procedures, and breach operations.
- **Deployment recovery:** C23 embeds the exact Git SHA, verifies `/health` after FTPS sync, and assigns known-good-SHA rollback and first-rollout snapshots to repository/Aruba administrators.
