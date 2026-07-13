# Architecture, Security, and Performance Review Tracking

Status: Draft for triage

Audit date: 2026-07-13

Audited revision: `34c1f55` (`main`)

Scope: application code, schema and migrations, deployment workflows, root hosting router,
tests, documentation, and operational scripts

This document is the mutable execution record for the post-remediation review. The
completed June 2026 [audit](audit.md), [roadmap](roadmap.md), and
[tracker](tracking.md) remain historical evidence. A completed item in those documents
does not establish that the same control is still present at this revision.

## Status legend

| Marker | Meaning |
|---|---|
| `[ ]` | Not started |
| `[/]` | In progress; only one PR should normally have this state |
| `[x]` | Merged and verified against its acceptance criteria |
| `[v]` | Implemented and locally verified; awaiting review/merge |
| `[!]` | Blocked by a recorded decision or external dependency |
| `[~]` | Superseded or deliberately accepted with a recorded rationale |

## Executive assessment

The codebase has a useful security baseline: strict types, prepared queries on the
reviewed request paths, consistent HTML escaping, CSRF checks on mutations, password
hashing, scoped entry creation, generic production errors, upload MIME allow-lists,
and a substantial automated test suite. No obvious request-derived SQL injection or
stored-XSS path was found during this static review. This is not a penetration test.

The most urgent risk is architectural, not a missing input check. Production and
development are mounted as `/prod` and `/dev` on the same host, while bootstrap starts
the default host-wide PHP session before it knows the application environment. Both
deployments can therefore address the same session record. A development admin session
can become production authentication if the PHP session backend is shared, as it is in
the inspected local PHP defaults. Production should not be exposed alongside development
until PR-01 is deployed and verified on the real host.

Release integrity is the second urgent area. The workflow disables FTPS certificate
verification, uploads directly into live directories, removes `vendor/` before replacing
it, does not reliably retire stale files, and verifies only database connectivity plus a
Git revision. Code can be intercepted in transit, requests can see a mixed release, old
PHP entry points can survive indefinitely, and an application/schema mismatch can still
report healthy.

The main data-integrity risks are CSV round trips that duplicate every athlete without a
membership number, missing database enforcement that an entry's athlete belongs to the
same club, and a mutable closed-event state that can invalidate the meaning and retention
date of frozen snapshots. These require explicit product decisions before migrations are
written; they should not be patched with heuristic matching.

The recommended approach is incremental hardening, not a framework rewrite. Establish
session and release boundaries first, add database invariants next, then extract narrow
application services from controllers as behavior becomes covered by integration tests.

## Verification baseline

| Check | Result at audit | Interpretation |
|---|---|---|
| Working tree | Clean at audit start | Findings are against `34c1f55` |
| `composer check` code gates | Metadata, syntax, PHPCS, PHPStan, and PHPUnit passed | PHPUnit: 195 tests, 5,318 assertions |
| `composer audit --locked --abandoned=fail` | Not completed because Packagist DNS was unavailable in the sandbox | Full transitive advisory status remains unverified |
| Manual locked PHPUnit advisory check | Locked `phpunit/phpunit` is `11.5.55`; the [published affected range](https://packagist.org/packages/phpunit/phpunit/advisories) is `<11.5.50` | This checks one disclosed direct dev dependency, not the full lock file |
| `composer test:migrations` | Not run successfully; no MySQL server/listener was available | Clean, upgrade, repeat, locking, and failure recovery need MySQL verification |
| Runtime/hosting checks | Not performed | Apache rewrite/header behavior, FTPS CA validation, filesystem permissions, mail, backup expiry, and scheduler behavior require host validation |

## Current architecture

| Layer | Current implementation | Review consequence |
|---|---|---|
| Entry and routing | Root `index.php` selects `/prod`, `/dev`, or `/legacy`; `public/index.php` dispatches `routes/web.php` | The root hosting layer is part of the security boundary and needs application-level tests |
| Request handling | Framework-free controllers return `Request`/`Response` objects | The boundary is useful, but globals and direct `header`/`exit` wrappers still bypass it |
| Authentication | PHP session keys (`club_id`, `is_admin`) checked repeatedly in controllers | Environment and principal isolation are implicit and currently unsafe |
| Domain/data | Static model methods plus SQL in models, services, and `AdminController` | Transactions and invariants are spread across layers; models often over-fetch |
| Presentation | PHP views with `LayoutContext` and localization helpers | Views are mostly passive, but layout authentication adds a query to rendered requests |
| Persistent state | MySQL, web-root event uploads, PHP sessions, and file logs | Deployment must preserve state while preventing stale executable files |
| Delivery | GitHub Actions builds an artifact and mirrors it over explicit FTPS commands | The live sync is not atomic and currently weakens transport verification |

## Finding register

Severity means the plausible impact in the documented same-host production topology,
not the effort required to fix it.

### Security and privacy

| ID | Severity | Finding and evidence | Impact | Planned PR |
|---|---|---|---|---|
| SEC-01 | Critical | `src/bootstrap.php:5-18` starts a default name, `/`-path session before `.env` is loaded, while root `index.php:64-94` serves prod and dev under one host. No environment binding, distinct backend, or strict mode is configured. | A session identifier created in development can address production session state and carry admin/club authentication and CSRF data across environments. | PR-01 |
| SEC-02 | High | Club login sets `club_id` without clearing `is_admin`; admin login sets `is_admin` without clearing `club_id`. Password changes do not revoke existing sessions, and there is no idle or absolute lifetime. | One browser can retain two principals; stolen or previously issued sessions remain valid after a credential reset. | PR-02, PR-03 |
| SEC-03 | High | `.github/workflows/deploy.yml:125,171,190,286,332` sets `ssl:verify-certificate false`. Third-party actions use mutable major tags although the historical tracker records immutable pins. | A man-in-the-middle can steal deployment credentials or alter code/configuration; action-tag movement can change privileged CI code without repository review. | PR-04 |
| SEC-04 | High | Forgot-password delivery sends to the submitted login email, not the separately stored `recovery_email`. Token invalidation and insert are not one transaction, and reset pages lack an explicit no-store/referrer policy. | Recovery policy is not enforced, concurrent/failing issuance can remove a valid token or leave multiple tokens, and token URLs can persist in browser/proxy history. | PR-10 |
| SEC-05 | Medium | Login does not perform equivalent password work for an unknown identity. Known forgot-password requests add database writes and synchronous mail. Throttling combines account and network into one key and checks then records separately. | Timing can disclose account existence; rotating either accounts or networks weakens limits; concurrent attempts can exceed the intended threshold. | PR-11 |
| SEC-06 | High | Public club self-registration has no verification/approval state, creation throttle, athlete quota, or registration quota. | An unauthenticated actor can create synthetic clubs, import thousands of athlete records, and generate application/database/mail load. | PR-12 |
| SEC-07 | Medium | Root HTTP redirects reflect the raw `Host` header. Security headers live in `public/.htaccess`, but root-routed dynamic requests require `prod/public/index.php` directly and are not shown to traverse that file. CSP and Permissions-Policy are absent. | Host-header redirects can be poisoned, and production dynamic responses are not guaranteed the documented browser protections. | PR-14 |
| SEC-08 | Medium | An authenticated club can request entry metadata for an unpublished event because `EventController::entries` uses unrestricted event lookup. The public club list exposes named contacts, and the athlete data-rights declaration checkbox is not persisted. | Draft metadata and unnecessary personal data can be disclosed; the privacy notice claims evidence the system does not retain. | PR-13 |
| SEC-09 | Medium | Event documents are stored below `public/uploads/events`; file writes and database updates are not one recoverable workflow. Cleanup failure after commit can be reported as a save failure or leave orphans. | Same-origin active-content and content-sniffing risks depend on host configuration; failures produce misleading state and retained files. | PR-15 |
| SEC-10 | Medium | Administration uses one environment-defined username/password and a boolean session flag, with no individual operator identity or second factor. | Credentials cannot be revoked per person, privileged actions cannot be attributed reliably, and one secret compromise grants all administration. | PR-32 |

### Deployment, database, and operations

| ID | Severity | Finding and evidence | Impact | Planned PR |
|---|---|---|---|---|
| DEP-01 | High | Deployment uses `lftp mirror -R` without deletion/state tracking. `docs/deployment.md:149-155` instead describes action-owned state files and reliable stale-code retirement. | Removed PHP files can remain remotely reachable; operators have a false runbook for diagnosing cleanup. | PR-05 |
| DEP-02 | High | Production and development are mirrored directly into live directories, with `vendor/` deleted before upload and runtime `.env` uploaded separately. Workflow concurrency also cancels an in-progress run. | Requests can execute with a missing autoloader, mixed old/new files, or code/config from different revisions; cancellation can strand that partial state. | PR-06 |
| DEP-03 | High | Deployment does not run or gate migrations. `/health` checks the embedded revision and `SELECT 1`, not the required schema version. | New code can be activated against an incompatible schema and still pass post-deploy health verification. | PR-07 |
| DEP-04 | High | `MigrationRunner` wraps MySQL DDL in a transaction even though MySQL DDL implicitly commits, has no advisory lock/checksum, and development bootstrap runs it on web requests. | A failed, concurrent, or edited migration can leave a partially applied schema that is neither detected, safely rolled back, nor safely retried. | PR-08, PR-09 |
| DEP-05 | High | The same `DB_USER`/`DB_PASS` are used at runtime and by the migration CLI, while migrations require DDL privileges. Environment-file loading can override process-provided values. | A compromised web request inherits schema-changing privileges; separate CI/CLI migration credentials cannot reliably take precedence. | PR-08 |
| DEP-06 | Medium | `render-deploy-env.php` creates the directory with mode `0700` but writes `.env` without forcing/verifying `0600`. LFTP command strings interpolate operator-controlled values. | Secret readability depends on runner/FTP umask, and quoting-sensitive credentials or paths can break or alter commands. | PR-04 |
| OPS-01 | Medium | Health does not check schema compatibility, required writable state, or a bounded dependency timeout. Repeated configuration/health failures can log on every request. | Monitoring can be green during an unusable release or can amplify a fault into disk exhaustion. | PR-07, PR-30 |
| OPS-02 | Medium | Only entry snapshots have an application purge. Expired/used reset tokens and stale throttle records can persist; log rotation, backup expiry, scheduler execution, and recovery tests are external assertions without recorded checks. | Security tables and logs grow indefinitely, and stated privacy/restore properties can silently drift from reality. | PR-20, PR-30 |

### Data integrity and correctness

| ID | Severity | Finding and evidence | Impact | Planned PR |
|---|---|---|---|---|
| DATA-01 | High | CSV import treats optional `membership_number` as its only update identity. Rows with no number always insert (`AthleteCsvTransfer::persist`, lines 272-307). | Exporting and re-importing a valid club archive duplicates every athlete whose membership number is empty. | PR-16, PR-17 |
| DATA-02 | High | There is no database unique constraint for `(club_id, membership_number)`. UI writes can create duplicates, CSV selects the first match, and concurrent imports race. | Updates can target an arbitrary duplicate and two valid-looking requests can violate the application invariant. | PR-16 |
| DATA-03 | High | `entries.club_id` and `entries.athlete_id` have independent foreign keys; the database does not prove that the athlete belongs to the entry's club. | Current repository checks can be bypassed by future code, scripts, imports, or manual operations, creating cross-club personal-data records. | PR-18 |
| DATA-04 | High | An administrator can edit identity/date fields on a closed event and reopen it. Snapshots are only created on open-to-closed transition, while retention filters mutable `closed` and `snapshot_at`. | Frozen athlete categories can disagree with the displayed event; reopening can expose live data and postpone or bypass the stated one-year purge. | PR-19 |
| DATA-05 | Medium | Deleting a club or athlete cascades closed entries and their snapshots. This may implement erasure, but conflicts with an assumed fixed closed-event record unless explicitly chosen. | Retention, sporting-record, and data-subject-erasure behavior is accidental rather than a documented policy. | PR-20 |
| DATA-06 | Medium | Direct forms do not consistently enforce schema-length and realistic numeric limits. Dates use server-local `date()` while other security timestamps use UTC; no `APP_TIMEZONE` is configured. PDO does not explicitly disable emulated prepares or establish strict session settings. | Results can differ by server timezone/SQL mode; bad values reach database errors or truncation instead of deterministic validation. | PR-21 |
| DATA-07 | Medium | The privacy declaration is not versioned/persisted, reset tokens are never routinely purged, and there is no administrator audit trail for event state or personal-data changes. | The system cannot demonstrate who accepted which declaration or reconstruct sensitive administrative changes; security data accumulates. | PR-13, PR-20, PR-30 |
| DATA-08 | Medium | Club registration checks for an existing name before insert, but the schema has no normalized unique club-name constraint. | Concurrent requests can create duplicates even though the UI treats the name as unique, making club identity ambiguous. | PR-12 |

### Performance and scalability

| ID | Severity | Finding and evidence | Impact | Planned PR |
|---|---|---|---|---|
| PERF-01 | High | Event registration loads every athlete for a club and accepts an unbounded, non-deduplicated ID list. Event entry pages load all registrations for an event. | Large clubs/events cause high memory and render time; duplicate POST IDs cause needless database attempts and log noise. | PR-22, PR-23 |
| PERF-02 | High | CSV import allows 5,000 rows and executes a lookup plus a write per row. Export loads all athlete objects and materializes the complete CSV string in memory. | One request can issue about 10,000 statements and hold a large transaction/session lock; repeated imports make export size unbounded. | PR-17 |
| PERF-03 | Medium | Club authentication queries `email` although the schema index is on generated `normalized_email`. Models frequently use `SELECT *`; public club/layout reads hydrate password and recovery fields they do not need. | Login can scan clubs, and sensitive unused columns consume memory and cross more application layers than necessary. | PR-24 |
| PERF-04 | Medium | Bootstrap starts and locks a PHP session for every dynamic request, including anonymous/health requests. Authenticated layout rendering also reloads the club. | Unnecessary cookies/files are created and concurrent requests sharing a session serialize for their full duration. | PR-03, PR-24 |
| PERF-05 | Medium | Closing an event selects all entries then updates each row. Retention deletes an unbounded result set and filters `snapshot_at` without a matching leading index. | Large events and overdue retention runs create long transactions, lock contention, and timeout risk. | PR-20, PR-25 |
| PERF-06 | Low | CSS and images receive one-month `immutable` caching without content-hashed names; the initial image payload is relatively large. | Changed assets can remain stale for a month, while first-page transfer is larger than necessary. | PR-26 |

### Architecture, consistency, and quality

| ID | Severity | Finding and evidence | Impact | Planned PR |
|---|---|---|---|---|
| ARCH-01 | Medium | `AdminController` combines authorization, validation, uploads, SQL, transactions, snapshots, cleanup, and rendering. Static active-record methods and controller SQL split persistence ownership. | Transaction boundaries are hard to reason about and business rules are difficult to test without controller/database fixtures. | PR-28 |
| ARCH-02 | Medium | Authorization guards are repeated in controllers; session keys are the principal model. `$_FILES`, `$_GET`, `date()`, `header`, and `exit` remain in application/wrapper paths despite `Request`/`Response` abstractions. | Policy changes are easy to apply inconsistently and tests depend on global state. | PR-27, PR-28 |
| ARCH-03 | Low | Unused admin views remain; compatibility wrappers and redirects bypass the response path. README calls `/event_entries.php` public even though the controller requires a principal. | Route ownership and supported behavior are ambiguous; dead code increases review surface. | PR-29 |
| QUAL-01 | Medium | Deployment regression coverage was reduced to a permissive mutable-action-tag assertion. The workflow has a duplicate `dev` trigger and no longer matches the completed tracker/runbook claims. | Previously fixed release controls can regress while tests and historical status still appear green. | PR-04, PR-05, PR-29 |
| QUAL-02 | Medium | PHPStan level 6 omits Security, Presentation, bootstrap, routes, scripts, views, and localization; PHPCS omits views/lang. Many database tests use SQLite or mocks for MySQL-specific behavior. | Important entry/security code has a weaker static gate and MySQL DDL/constraint semantics can diverge from tests. | PR-31 |
| QUAL-03 | Medium | README requires PDO MySQL, mbstring, fileinfo, and XML, but `composer.json` declares only PHP and health does not preflight the extensions. | Composer/CI can accept an artifact that fails only after a missing extension's code path is reached in production. | PR-31 |

## Decisions required

These decisions block only the PRs listed. Earlier independent containment work should
continue without waiting for all decisions.

| ID | Owner | Needed by | Decision |
|---|---|---|---|
| D-01 | Product/data owner | PR-16 | Choose CSV identity: require a unique membership number, add a stable opaque athlete export key, or make append/replace behavior explicit. Do not use name/date matching. |
| D-02 | Product/privacy/legal | PR-19, PR-20 | Decide whether closing is terminal, who may reopen, which fields remain mutable, and how erasure interacts with closed sporting records and the one-year limit. |
| D-03 | Product/operations | PR-12 | Choose club activation (verified email, administrator approval, or invitation) and practical per-club athlete/entry quotas. |
| D-04 | Hosting/operations | PR-06 | Confirm whether Aruba FTP supports same-filesystem rename/symlink or another atomic switch. If not, approve a tested maintenance-window protocol. |
| D-05 | Privacy/product | PR-13 | Confirm whether contact first/last names are intentionally public or should be reduced to club name/federal code/contact channel. |
| D-06 | Hosting/operations | PR-15 | Confirm availability of private storage outside the document root and whether Apache can serve authorized files through the application. |
| D-07 | Product/operations | PR-32 | Select individual administrator authentication: local accounts plus a second factor, passkeys, or an external identity provider; define enrollment and break-glass recovery. |

## Incremental PR plan

Each row is intentionally scoped to one deployable behavior. The status is changed only
after the acceptance criteria and relevant shared gates pass. Migrations are forward-only;
destructive cleanup follows a measured compatibility window in a later PR.

### Phase 0: immediate containment

| PR | Status | Title and bounded scope | Dependencies | Acceptance criteria | Resolves |
|---|---|---|---|---|---|
| PR-01 | `[v]` | `fix(session): isolate deployment environments` - load typed environment identity before session start; use distinct cookie namespace/path and session backend namespace or an environment-bound session record; enable strict mode. | None | A real shared-handler integration test proves a dev session ID cannot authenticate prod even if copied manually; cookie flags/path/name are correct through root routing. | SEC-01 |
| PR-02 | `[v]` | `fix(auth): make session principals exclusive` - centralize club/admin login transitions, clear all prior auth and flash/CSRF state, regenerate the ID, and define one typed principal. | PR-01 | Switching club/admin in either direction leaves exactly one principal; fixation and dual-role regression tests pass. | SEC-02 |
| PR-03 | `[!]` | `fix(auth): revoke and bound sessions` - add credential/session versioning, idle and absolute expiry, lazy session start, and early session close after mutation. | PR-02 | Password reset/change invalidates prior sessions; expiry uses an injectable clock; anonymous health/public requests do not emit or lock a session. | SEC-02, PERF-04 |
| PR-04 | `[!]` | `fix(deploy): authenticate the delivery chain` - enable FTPS CA/hostname verification, pin actions by immutable SHA, force/verify `.env` permissions, and remove unsafe command interpolation where feasible. | None | A bad certificate fails closed; all third-party `uses` references are SHA-pinned with version comments; artifact/env tests verify permissions and secret exclusion. | SEC-03, DEP-06, QUAL-01 |
| PR-05 | `[ ]` | `fix(deploy): retire stale executable files` - implement manifest/state-based deletion limited to owned code paths, preserve `.env`/uploads/logs/legacy, restore structural workflow tests, and correct the runbook. | PR-04 | A fixture deployment removes a file absent from the new manifest and preserves every server-owned path; docs describe the actual mechanism. | DEP-01, QUAL-01 |
| PR-06 | `[ ]` | `fix(deploy): activate complete releases atomically` - upload to a revision directory, verify it, then switch the routed release; otherwise implement the approved maintenance protocol. | D-04, PR-05 | Concurrent probes never observe missing `vendor`, mixed revisions, or mismatched `.env`; failed activation leaves the prior release serving. | DEP-02 |

### Phase 1: schema-safe releases

| PR | Status | Title and bounded scope | Dependencies | Acceptance criteria | Resolves |
|---|---|---|---|---|---|
| PR-07 | `[ ]` | `feat(health): require schema compatibility` - publish code's minimum/maximum schema contract, check it with bounded timeouts, and gate activation using expand/contract rules. | PR-06 | Health is false for incompatible/missing schema and true for supported old/new versions; a simulated failed check keeps the old release active. | DEP-03, OPS-01 |
| PR-08 | `[ ]` | `fix(database): separate migration authority` - add CLI-only migration credentials with real-environment precedence, restrict runtime grants, remove request-time migrations, and document the release order. | PR-07 | Runtime credentials cannot execute DDL; migration CLI can; web requests never mutate schema; secrets remain redacted. | DEP-04, DEP-05 |
| PR-09 | `[ ]` | `fix(migrations): serialize and resume MySQL DDL` - add an advisory lock, immutable checksums, statement/migration recovery semantics, schema preconditions, and concise connection errors without claiming transactional DDL rollback. | PR-08 | MySQL clean/upgrade/repeat and induced mid-migration failure tests converge safely; edited history fails closed; two runners cannot apply concurrently. | DEP-04 |

### Phase 2: authentication and privacy boundaries

| PR | Status | Title and bounded scope | Dependencies | Acceptance criteria | Resolves |
|---|---|---|---|---|---|
| PR-10 | `[ ]` | `fix(recovery): enforce the recovery channel` - issue and invalidate tokens transactionally, deliver only to stored `recovery_email`, add no-store/no-referrer responses, purge expired/used tokens, and rotate credential version on reset. | PR-03 | Submitted aliases never receive mail; one active token remains under concurrency; reset URLs are not cached; old sessions fail. | SEC-04, DATA-07 |
| PR-11 | `[!]` | `fix(auth): make throttling atomic and identity-safe` - consume independent account and network buckets atomically, perform dummy password work, bound cleanup, and equalize forgot-password response work where practical. | PR-02 | Concurrency tests cannot exceed the limit; either bucket can block; known/unknown response bodies and timing work are equivalent within a documented tolerance. | SEC-05 |
| PR-12 | `[ ]` | `feat(clubs): add controlled activation and quotas` - implement the selected activation state, registration throttles, normalized club-name identity, and hard athlete/entry/import quotas with administrator visibility. | D-03, PR-11 | An unverified/unapproved club cannot authenticate or create data; concurrent duplicate names fail at the database; limits are enforced server-side and tested without relying on form controls. | SEC-06, DATA-08 |
| PR-13 | `[ ]` | `fix(privacy): minimize and evidence club data` - require published events for club entry views, persist a versioned rights declaration, and apply the public-contact decision. | D-05 | Draft event metadata is admin-only; declaration records actor/time/text version; public queries select only approved fields. | SEC-08, DATA-07 |
| PR-14 | `[v]` | `fix(http): canonicalize hosts and response policy` - allow-list hosts, emit headers in the application/root path, mark authenticated/personal responses no-store, add CSP in report-only then enforceable form, Permissions-Policy, no-sniff, frame protection, HSTS on HTTPS, and remove obsolete policy. | PR-01 | Host-header tests cannot produce external redirects; headers exist on root-routed dynamic/error responses; sensitive pages are not cached; CSP reports are clean before enforcement. | SEC-07 |
| PR-15 | `[ ]` | `fix(files): make event documents private and recoverable` - store outside web root where supported, stream through an authorized response, and record post-commit cleanup/reconciliation work. | D-06 | Direct URL execution is impossible; content type/disposition are controlled; induced DB/file failures converge without false success/failure messages or untracked orphans. | SEC-09 |

### Phase 3: database invariants and event lifecycle

| PR | Status | Title and bounded scope | Dependencies | Acceptance criteria | Resolves |
|---|---|---|---|---|---|
| PR-16 | `[ ]` | `fix(athletes): establish stable import identity` - implement D-01, preflight existing duplicates/null behavior, normalize values, and add the appropriate database uniqueness constraint/key. | D-01, PR-09 | Migration fails with an actionable duplicate report before DDL; concurrent writes cannot create duplicate identities; direct UI and CSV use the same rule. | DATA-01, DATA-02 |
| PR-17 | `[ ]` | `perf(csv): make transfer idempotent and bounded` - preload identities or use safe native upsert, deduplicate input, stream exports, and define append/replace semantics without a request-sized response string. | PR-16 | Export/import/export is idempotent including missing membership numbers; query count is bounded independently of rows or uses measured batches; memory stays bounded at 5,000 rows. | DATA-01, PERF-02 |
| PR-18 | `[ ]` | `fix(entries): enforce club-athlete ownership in MySQL` - add the supporting composite athlete key and composite entry foreign key after a mismatch preflight. | PR-09 | Invalid historical rows are reported before migration; MySQL rejects cross-club entries from every write path; valid cascades remain covered. | DATA-03 |
| PR-19 | `[ ]` | `fix(events): introduce an explicit lifecycle` - centralize state transitions, immutable `closed_at`, row locking, allowed post-close fields, and the approved reopen policy. | D-02, PR-09 | Illegal transitions and post-close identity/date edits fail; close and snapshot commit together; concurrent closes are deterministic; retention time cannot be refreshed by toggling state. | DATA-04 |
| PR-20 | `[ ]` | `fix(retention): implement the approved record policy` - apply D-02 to erasure/cascades, batch indexed snapshot/token/throttle cleanup, and record purge evidence. | D-02, PR-10, PR-19 | Boundary-time tests use an injected clock; purge is restartable/batched; erasure and retained closed records match the documented policy; scheduled runs expose metrics/status. | DATA-05, DATA-07, OPS-02, PERF-05 |
| PR-21 | `[ ]` | `fix(validation): align commands, schema, and time` - shared command validators, schema-length/range limits, `APP_TIMEZONE=Europe/Rome`, injected clocks, native prepares, strict SQL session, and connection timeouts. | PR-09 | Form/CSV constraints agree; Italian date boundaries are deterministic; invalid data never depends on MySQL truncation; PDO settings are asserted. | DATA-06 |

### Phase 4: bounded hot paths

| PR | Status | Title and bounded scope | Dependencies | Acceptance criteria | Resolves |
|---|---|---|---|---|---|
| PR-22 | `[ ]` | `perf(registration): bound athlete selection` - searchable/keyset athlete pages, a maximum deduplicated ID set, and one set-oriented registration result. | PR-18, PR-21 | Large-club fixture renders bounded rows; duplicate/oversized POSTs are deterministic; query count is bounded and ownership remains enforced. | PERF-01 |
| PR-23 | `[ ]` | `perf(entries): paginate event records` - keyset or bounded pages for UI plus a separately streamed authorized report where full output is required. | PR-18 | A large event never hydrates all rows for an HTML request; ordering/grouping and club/admin visibility remain stable between pages. | PERF-01 |
| PR-24 | `[!]` | `perf(reads): add projections for principals and lists` - query normalized email, introduce purpose-specific row/DTO projections, and stop hydrating password/recovery fields for public/layout reads. | PR-02 | Login uses the normalized index under MySQL `EXPLAIN`; public and layout queries exclude credential/recovery columns; authenticated rendering avoids duplicate club loads. | PERF-03, PERF-04 |
| PR-25 | `[ ]` | `perf(events): batch snapshot writes` - replace one-update-per-entry close work with measured batches/set operations and bounded transactions. | PR-19 | Query/transaction counts remain bounded at representative event size; close remains atomic from the application's perspective and category parity tests pass. | PERF-05 |
| PR-26 | `[v]` | `perf(assets): fingerprint and resize static media` - content-hashed asset URLs/manifest, correct cache policy, responsive optimized images, and measured size budgets. | None | A changed asset produces a new URL; unchanged hashes are immutable; initial transfer meets a recorded desktop/mobile budget. | PERF-06 |

### Phase 5: architecture, consistency, and assurance

| PR | Status | Title and bounded scope | Dependencies | Acceptance criteria | Resolves |
|---|---|---|---|---|---|
| PR-27 | `[v]` | `refactor(auth): use route guards and AuthContext` - move repeated controller checks into route middleware/guards and replace raw session-key reads with the typed principal. | PR-02 | Every protected route declares its policy once; anonymous/club/admin matrix tests cover the real router; no controller reads auth keys directly. | ARCH-02 |
| PR-28 | `[ ]` | `refactor(events): extract one event use case` - move event save/transition/upload coordination from `AdminController` into a transaction-aware application service and repository; adapt `Request` file input. | PR-15, PR-19, PR-27 | Controller orchestrates HTTP only; service tests cover create/edit/close/failure paths; SQL and `$_FILES` are absent from the controller. | ARCH-01, ARCH-02 |
| PR-29 | `[ ]` | `chore(routes): remove dead surfaces and align docs` - delete unused views, replace direct header/exit wrappers with router responses where compatibility is required, fix route access documentation, and remove duplicate workflow syntax. | PR-05, PR-27 | Every public PHP file has a tested role; route inventory and README access matrix match dispatch authorization; no unsupported template remains. | ARCH-03, QUAL-01 |
| PR-30 | `[ ]` | `feat(operations): make controls observable` - structured admin audit events, health/log deduplication, purge/backup/rotation status, and a restore-test runbook with owners. | PR-07, PR-20 | Sensitive mutations record actor/action/target/time without secrets; repeated faults are rate-limited; stale scheduled/backup checks alert; a restore drill is recorded. | OPS-01, OPS-02, DATA-07 |
| PR-31 | `[ ]` | `test(quality): widen static, platform, and MySQL assurance` - declare required PHP extensions, extend PHPStan/PHPCS/syntax scope incrementally, restore deployment invariant tests/actionlint, and add MySQL integration coverage for new constraints/lifecycle. | PR-09, PR-18, PR-19 | Composer rejects a missing runtime extension; all application/security entry paths are in a static gate; workflow tests fail on mutable actions or missing release controls; MySQL CI exercises clean/upgrade/repeat paths. | QUAL-02, QUAL-03 |
| PR-32 | `[ ]` | `feat(admin): make privileged identities individual` - replace the shared environment principal with named administrators, the selected strong second factor, revocation, and auditable break-glass recovery. | D-07, PR-11, PR-27 | Two administrators have distinct credentials/audit identities; one can be revoked without affecting the other; second-factor and recovery negative-path tests pass; no shared boolean-only principal remains. | SEC-10 |

## PR execution rules

1. Keep one behavior change per PR. Target fewer than 400 changed production lines unless a generated migration/test fixture explains the exception.
2. Add the failing regression test first or in the same commit; do not defer tests to a later PR.
3. For schema work, use expand/migrate/contract. Include duplicate/invariant preflight, clean install, real upgrade, repeat, and rollback/forward-recovery notes.
4. Preserve old code compatibility until the new schema is active. Never solve a failed deploy by reversing a data migration.
5. Run `composer check` for every PR. Run MySQL migration tests for schema/query work and artifact/root-router tests for delivery work.
6. Record query count, memory, or transfer baselines before performance changes and assert a concrete budget afterward.
7. Update this tracker with the PR URL, merge SHA, verification output, operational action, and any changed decision. Historical trackers remain unchanged.
8. A security control is complete only after its negative test passes. Configuration or documentation alone is not sufficient.

## Exit criteria

| Milestone | Required evidence |
|---|---|
| Safe dual environment | PR-01 through PR-05 complete; production host proves session separation and authenticated FTPS |
| Safe releases | PR-06 through PR-09 complete; an induced failure leaves the old compatible release live |
| Defensible auth/privacy | PR-10 through PR-15 complete; decisions D-03, D-05, and D-06 are recorded |
| Enforced data model | PR-16 through PR-21 complete; MySQL rejects identity/ownership violations and lifecycle/retention tests use real time boundaries |
| Bounded operation | PR-22 through PR-26 complete with recorded representative-size budgets |
| Maintainable baseline | PR-27 through PR-32 complete; route policy, event use case, static gates, individual privileged identity, audit evidence, and runbooks agree |

## Session log

| Date | Activity | Result | Verification | Next action |
|---|---|---|---|---|
| 2026-07-13 | Post-remediation static architecture/security/performance review | Drafted findings, decisions, and 32 incremental PRs against `34c1f55` | Code gates passed through PHPUnit; dependency audit and live MySQL/hosting checks remain explicitly unverified | Triage SEC-01 and approve PR-01 |
| 2026-07-13 | PR-01 session isolation | Implemented environment-specific cookie/context boundary and root-prefix propagation; pending review/merge | PHPUnit 198/5,340; PHPStan; PHPCS; metadata/syntax; root staging; production artifact bilingual boot | Review/merge PR-01, deploy once, verify cross-environment login isolation, then begin PR-02 |
| 2026-07-13 | PR-01 commit | `870bc7c` created; remote dry-run succeeds without modifying `origin` | Full pre-push gate: migration smoke, 198 tests/5,340 assertions, 76.2% changed coverage, artifact build/boot, audit | Await review/merge and production verification |
| 2026-07-13 | PR-02 exclusive principals | `7652a99` created; club/admin login clears the other role and rotates ID/CSRF | Focused 5/30; full 200 tests/5,349 assertions; PHPStan; PHPCS; remote dry-run | Await review/merge, then begin PR-03 |
| 2026-07-13 | Deferred input | PR-03 needs approved idle/absolute session policy and credential-version schema release plan; PR-04 needs verified Aruba FTPS hostname/CA behavior; PR-11 needs a durable asynchronous mail strategy to make known/unknown recovery work equivalent | No safe implementation assumption | Continue independent PR-27; revisit blocked PRs last |
| 2026-07-13 | PR-27 route guards and AuthContext | `3a39081` created: route-level club/admin/authenticated policies now guard all protected MVC routes; controllers/layout read `AuthContext`, with temporary legacy session compatibility confined there | Migration smoke; 201 tests/5,359 assertions; PHPStan; PHPCS; locked audit; artifact manifest/Italian-English boot; changed coverage 76.9% (40/52); remote dry-run negotiated `34c1f55..3a39081` | Review/merge, then continue independent PR-14/PR-24/PR-26 |
| 2026-07-13 | Deferred input | PR-14 needs the complete production host allow-list and a CSP report endpoint/observation owner; PR-24 needs a representative MySQL instance for the required normalized-email `EXPLAIN` verification | No safe host policy or MySQL plan can be inferred from this workspace | Continue PR-26; revisit deferred PRs last |
| 2026-07-14 | PR-14 host and response policy | `7657f16` created: allow-listed canonical/redirect hosts before HTTPS redirect; application headers now include report-only CSP, Permissions-Policy, no-sniff/frame/referrer policy, authenticated no-store, and root HTTPS HSTS | 201 tests/5,361 assertions; PHPStan; PHPCS; locked audit; remote dry-run negotiated `34c1f55..7657f16` | Deploy report-only CSP, observe violations before any enforcement change |
| 2026-07-14 | PR-26 asset fingerprinting | `aa18a8c` created: content-hash asset URLs and a 1280px WebP background (126,858 bytes) replace the 371,561-byte JPEG on event pages; recorded budgets are 500 KB desktop and 300 KB mobile | 201 tests/5,361 assertions; focused rendering tests; remote dry-run negotiated `34c1f55..aa18a8c` | Review/merge and measure real browser transfer after deployment |
