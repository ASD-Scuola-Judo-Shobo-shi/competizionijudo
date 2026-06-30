# Project Audit

Audit date: 2026-06-28
Audited revision: `e975cb3` (`main`, one commit ahead of `origin/main` when the audit started)
Scope: current tracked application, current ignored local files that can affect builds, migrations, tests, CI/CD, and deployment packaging.

## Executive summary

The repository has a coherent small MVC shape, consistent prepared-statement usage, output escaping in most templates, CSRF helpers, hardened session-cookie defaults, typed PHP, static analysis, coding standards, and a CI definition. Those are useful foundations.

The application is not production-ready in its current state. The most urgent issues are:

1. **Critical account takeover:** the forgot-password response displays the reset token to anyone who submits a registered email (`S-01`).
2. **Critical cross-club authorization failure:** a club can register another club's athlete by posting an arbitrary athlete ID (`S-02`).
3. **Critical deployment failure:** the deploy artifact excludes `lang/`, so normal page rendering will fail after a clean deployment (`R-01`).
4. **High-risk destructive actions:** club, event, and athlete deletion use authenticated GET requests; athlete add/edit does not validate its CSRF token (`S-03`).
5. **Broken release gate:** `composer check` currently fails PHPStan, while the independent deployment workflow does not run that gate and can deploy anyway (`Q-01`, `R-04`).
6. **Broken fresh schema:** application queries require `athletes.weight_category`, but no migration creates it (`R-02`).
7. **Incorrect competition data model:** category data is calculated against the current year when an athlete is edited, then reused for events in other years (`A-02`).

Security and deployment remediation should precede feature work. The ordered implementation is in [roadmap.md](roadmap.md); execution state belongs in [tracking.md](tracking.md).

## Verification performed

| Check | Result | Interpretation |
|---|---|---|
| Repository inventory and route/model/view tracing | Completed | 80 tracked PHP files, 5 SQL migrations, framework-free MVC, MySQL/PDO |
| `composer check` | **Failed** | PHPStan reports a non-exhaustive `match` in `src/Model/Belt.php:207`; tests and dependency audit were not reached |
| `composer test` | **Failed in the default environment** | Session files target read-only `/var/lib/php/sessions`; one state-dependent test fails and 30 warnings are emitted |
| `php -d session.save_path=/tmp vendor/bin/phpunit` | Passed | 31 tests, 92 assertions; confirms the suite itself passes when session storage is writable |
| `composer audit --locked --no-interaction` | Not verified | Network/DNS access to Packagist was unavailable; this is an audit limitation, not evidence of no advisories |
| Shell syntax checks | Passed | Deploy and Git hook scripts parse successfully |
| `bash scripts/build-deploy.sh` | **Failed locally** | `rsync -a` attempted unsupported group preservation; the partial artifact was still inspectable |
| Partial deploy artifact inspection | **Failed requirements** | `lang/`, `migrations/`, and `scripts/` are absent; an ignored `AdminController.php.bak` was included locally |

No live production system, production database, mail transport, or hosting control panel was inspected. Findings about runtime data and host-provided environment variables are therefore based on repository behavior and are called out where conditional.

## Architecture overview

The request path is:

`public/index.php` -> `Application` -> `Router` -> controller -> static model/PDO calls -> PHP view -> shared layout -> `Response`.

The structure is suitable for a small application, but boundaries are porous:

- Controllers perform authentication, validation, SQL, file storage, domain calculation, and response orchestration.
- Models combine records, query methods, and domain logic through static methods.
- Views perform database queries and session-dependent model loads.
- Global helpers provide configuration, environment loading, CSRF, localization aliases, navigation, and rendered pagination HTML.
- Deployment and migration behavior is partly implicit in bootstrap code.

This does not require adopting a large framework. The simpler target is to preserve the current MVC shape while making authorization, mutation handling, validation, and domain calculations explicit and testable.

## Positive foundations

- PHP 8.2 baseline, strict types, PSR-4 autoloading, and readonly record properties.
- Prepared statements are used for request-derived SQL values. The few interpolated pagination integers are cast first.
- Most user-controlled output is passed through `e()`.
- Passwords use `password_hash()` and `password_verify()`.
- Reset tokens are random and stored as SHA-256 hashes rather than plaintext.
- Session IDs regenerate after successful login; cookies are `HttpOnly` and `SameSite=Lax`, and become `Secure` when HTTPS is detected.
- Event uploads use server-side MIME detection, generated filenames, and non-PHP extensions.
- Foreign keys and a unique event/club/athlete entry key are present in the baseline schema.
- Public lists have some pagination and supporting indexes.
- CI, PHPCS, PHPStan, PHPUnit, Git hooks, and a deployment artifact smoke job already exist.

These controls reduce risk, but several README claims overstate their completeness.

## Findings index

| ID | Severity | Area | Finding |
|---|---|---|---|
| S-01 | Critical | Security | Password-reset tokens are returned to unauthenticated requesters |
| S-02 | Critical | Security | Event registration does not enforce athlete ownership |
| R-01 | Critical | Release | Deploy artifact omits runtime localization files |
| S-03 | High | Security | Destructive GET actions and incomplete CSRF enforcement |
| S-04 | High | Security | Login throttling is session-local and trivially bypassed |
| S-05 | High | Security | Event visibility and entry-list authorization are inconsistent |
| R-02 | High | Data | Fresh schema lacks a column required by all athlete writes/reads |
| R-03 | High | Release | Generated deployment environment omits required secrets/configuration |
| R-04 | High | Release | Deployment is not gated on the quality workflow |
| R-05 | High | Release | Migrations cannot be run from the deployed artifact |
| A-01 | High | Architecture | Claimed exports and entry-management routes are dead or unreachable |
| A-02 | High | Domain | Athlete categories are persisted for the wrong temporal context |
| P-01 | High | Performance | Club list executes an entries query once per athlete |
| Q-01 | High | Quality | The repository's advertised quality command is red |
| Q-02 | Medium | Quality | Tests are environment-dependent and mislabeled as integration tests |
| Q-03 | High | Quality | Database, authorization, migration, route, and artifact behavior is largely untested |
| S-06 | Medium | Security | Server-side validation and account identity constraints are weak |
| S-07 | Medium | Security | Internal exceptions are exposed and operational errors are not logged |
| S-08 | Medium | Security | Reset consumption and multi-athlete registration are not atomic |
| S-09 | Low | Security | Logout uses GET and session hardening is incomplete |
| A-03 | Medium | Architecture | SQL and session-dependent loading occur in views/layouts |
| A-04 | Medium | Architecture | File caches are stale after writes and add more risk than value |
| A-05 | Medium | Data | Migration runner silently accepts partial migrations |
| A-06 | Medium | Architecture | Router and language-switch flows bypass a single request/response contract |
| A-07 | Low | Simplicity | Domain/display definitions and dead classes are duplicated |
| A-08 | Medium | Documentation | README, environment example, and current behavior disagree |
| P-02 | Medium | Performance | Enum label rendering repeatedly reloads locale files |
| P-03 | Medium | Performance | Several queries are unbounded or scan data outside the displayed page |
| P-04 | Low | Performance | Query profiler is never populated |
| R-06 | Medium | Release | Root routing artifact checks the wrong source filename |
| R-07 | Medium | Release | Build packaging is non-portable and can include ignored backup files |
| R-08 | Medium | Release | Deployment has no health check or rollback signal |
| F-01 | Medium | Functionality | Registration deadlines and “upcoming” semantics are not enforced |
| F-02 | Medium | Functionality | Duplicate-registration warning is lost during redirect |
| F-03 | Low | Functionality | Homepage creates unused, hard-coded competition records |
| O-01 | Medium | Operations | Runtime uploads are tracked/deployed as application source |

## Security analysis

### S-01 — Password-reset account takeover (Critical)

Evidence:

- `src/Controller/ClubController.php:156-178` creates a valid reset token for a known email and always assigns the raw reset URL to `$devLink`.
- `views/club/forgot_password.php:17-20` renders that URL without checking the application environment.
- The unknown-email path returns a distinct error at `ClubController.php:178-180`, enabling account enumeration despite the generic success translation.

Impact: anyone who knows or guesses a club email can request its reset link, read the token in the response, and set a new password. No access to the email account is required.

Required fix: immediately stop returning tokens outside an explicitly local test environment, return the same response for known and unknown addresses, and disable production recovery until a real mail delivery path is configured. Add throttling, one-time atomic consumption, password policy, and regression tests before re-enabling it.

### S-02 — Cross-club athlete registration (Critical)

Evidence:

- `EventController.php:101-110` trusts posted `athletes[]` IDs and passes the session club ID separately.
- `Entry::register()` inserts both values without checking `athletes.club_id` (`src/Model/Entry.php:85-98`).
- The foreign keys validate that the club and athlete exist, but do not validate that they belong together.

Impact: an authenticated club can register an athlete belonging to any other club by changing a checkbox value. This corrupts competition data and crosses a tenant/privacy boundary.

Required fix: perform an atomic insert constrained by both athlete ID and owning club, or reject unless `Athlete::findById($athleteId, $clubId)` succeeds. Preserve the database unique constraint and handle duplicate-key errors as the authoritative race-safe result.

### S-03 — Unsafe mutations and incomplete CSRF (High)

Evidence:

- Admin club deletion uses `GET ?delete=` (`AdminController.php:83-86`; `views/admin/manage_clubs.php:26`).
- Admin event deletion uses `GET ?delete=` (`AdminController.php:114-117`; `views/admin/manage_events.php:39`).
- Athlete deletion uses `GET ?delete=` (`ClubAreaController.php:38-42`; both club area views).
- Athlete add/edit renders a CSRF field, but `ClubAreaController.php:48-75` never calls `validate_csrf()`.

Impact: a logged-in user can be induced to delete cascading club/event/athlete data through a top-level cross-site GET. Missing validation also makes the form's CSRF field cosmetic.

Required fix: give every mutation a dedicated POST route, validate CSRF before parsing mutation data, and render forms rather than destructive links. Return 405 for wrong methods where appropriate.

### S-04 — Bypassable throttling (High)

Both login controllers count failures only in the attacker's current session. Clearing the cookie or starting parallel sessions resets the limit. Forgot-password has no throttle. Use a persistent throttle keyed by a hash of account identifier plus network signal, with bounded retention and no raw identifiers in logs. Keep messages uniform and test time-window behavior.

### S-05 — Visibility and authorization inconsistencies (High)

- `EventController::show()` returns any event by ID without requiring `published=true`, exposing unpublished event data and uploaded files.
- `EventController::entries()` is currently unreachable, but its non-admin logic only forces the club filter when a non-zero `club` query is supplied. With no filter it would return all clubs' athletes (`EventController.php:158-163`). This must be fixed before routing the action.
- Entry data includes minors' birth dates, weights, belt, and membership numbers, so the default must be deny-by-default.

### S-06 — Validation and identity constraints (Medium)

- Club registration permits an empty federal code in PHP even though the database makes it unique and non-null.
- Club email is not unique, while login and reset use `SELECT ... WHERE email = ?` and take the first row.
- Passwords have no minimum length or compromised/common-password control.
- Athlete gender, belt, date, weight, and required names rely mostly on HTML controls or database errors.
- Event type, dates, deadline ordering, and upload size are not fully validated in controller code.

Validation should be server-side, deterministic, and return safe field errors. Add a data-cleanup preflight before introducing a unique normalized club-email constraint.

### S-07 — Error disclosure and missing logs (Medium)

Registration, login, reset, club edit, and event edit concatenate raw exception messages into user-visible output. Production `Application` hides uncaught errors but does not log them. This can expose schema/host details while leaving operators without a trace. Introduce a minimal logger with request correlation, log server-side context, and show generic localized messages.

### S-08 — Non-atomic security/data workflows (Medium)

Password update and token invalidation are separate statements without a transaction or conditional token claim. Multi-athlete event registration can partially succeed. Duplicate pre-check followed by insert is race-prone; the unique key prevents duplication, but the resulting PDO exception is not translated to the intended warning. Use transactions where the workflow must be all-or-nothing and treat constrained writes as authoritative.

### S-09 — Additional hardening (Low)

Logout is a state-changing GET. Session cookies lack an explicit application-specific name, strict mode, and idle/absolute expiry. HTTPS detection may be wrong behind a trusted proxy. Security headers omit a Content Security Policy and Permissions Policy; existing inline JS/CSS must be moved or nonced before a strict CSP can be enabled.

## Data and migration analysis

### R-02 — Fresh schema mismatch (High)

`Athlete::add()`, `Athlete::update()`, and entry queries require `weight_category`. The baseline `athletes` table at `migrations/20260619_000000_create_baseline_schema.sql:43-57` does not define it. The legacy-copy migration only tries to update the absent column and the runner suppresses that error; it never creates the column.

A clean install therefore reaches an inconsistent “migrations complete” state and athlete writes fail. Add a new forward-only migration rather than relying on edits to an already-applied historical migration, then verify a clean MySQL schema in CI.

### A-05 — Fail-open migration runner (Medium)

`MigrationRunner.php:66-74` ignores every “unknown column” error and still records the migration as applied. That behavior was apparently added to tolerate legacy copy SQL, but it can hide real defects. Its semicolon-based SQL splitting is also fragile, and MySQL DDL is not made atomic merely by the surrounding transaction.

Replace broad suppression with explicit schema-aware legacy handling. Migrations should either complete or remain pending. Test both a clean schema and a representative legacy upgrade.

### Account and relational integrity

The database should enforce normalized unique club emails after duplicates are resolved. The entry table should make the athlete/club relationship impossible to mismatch, either with an appropriate composite constraint/schema design or a single constrained insert that is always used. Redundant indexes should be reviewed: foreign keys and `unique_entry(event_id, club_id, athlete_id)` already cover prefixes added again by the performance migration.

## Release and deployment analysis

### R-01 — Incomplete deploy artifact (Critical)

`scripts/build-deploy.sh` includes `config`, `public`, `routes`, `src`, `vendor`, `views`, and selected `var` paths, but not `lang`. `Localization::loadMessages()` loads `base_path('lang/<locale>.php')`; a clean artifact will throw on the first translation and return a 500 page.

The CI smoke test checks only `.htaccess`, `public/index.php`, and the layout, so it does not detect this. Package all runtime files and boot the built artifact in the smoke test.

### R-03 — Incomplete environment contract (High)

The workflow writes a new `.env` containing only `APP_ENV`, `APP_DEBUG`, `APP_NAME`, and `APP_URL`. The application requires `DB_NAME` and requires `ADMIN_USER`/`ADMIN_PASS_HASH` for administration. Unless the host injects those as real process environment variables, every deployment overwrites the server configuration with an unusable file.

Treat `.env` as server-owned and exclude it from synchronization, or provision the complete secret set through a secure host mechanism. Do not store a secret-bearing `.env` in downloadable CI artifacts.

### R-04 — Deployment bypasses CI (High)

`ci.yml` and `deploy.yml` run independently on the same push. The deployment build validates Composer metadata but does not run `composer check`. The current PHPStan failure demonstrates that a red revision can still reach the deploy jobs. Gate deployment on the full quality suite in the same workflow or trigger deployment only from a successful CI workflow.

### R-05 — No migration path in artifact (High)

The artifact excludes `migrations/` and `scripts/`, while production intentionally does not auto-run migrations. `composer migrate` cannot work on the server. The development artifact also excludes migrations even though bootstrap tries to auto-run them. Package the migration assets and make migration execution an explicit, controlled release step; remove or honor the unused `APP_AUTO_RUN_MIGRATIONS` setting.

### R-06 to R-08 — Packaging and operational safety (Medium)

- The root-router staging step looks for `root.htaccess`, but the repository contains `.htaccess`; the uploaded root artifact is empty.
- Local `rsync -a` failed while preserving group metadata on this filesystem. Use portable ownership flags.
- Broad directory includes can package ignored files. The local ignored `src/Controller/AdminController.php.bak` appeared in the partial artifact.
- FTP deployment intentionally leaves stale remote files and has no post-deploy HTTP health check or rollback trigger.
- Third-party actions are version-tag pinned rather than commit-SHA pinned, leaving a supply-chain hardening opportunity.

## Performance analysis

### P-01 — Confirmed N+1 query (High)

`ClubAreaController` already loads all entries once. Nevertheless, `views/club/area_list.php:63-69` calls `Entry::findByClub()` again for every athlete and filters the full result in PHP. With `A` athletes and `E` entries this produces `A + 1` entry queries and repeated `O(A*E)` filtering.

Build registration counts once in the controller (or one grouped SQL query), pass a map to the view, and prohibit model calls from templates.

### P-02 — Localization reload loop (Medium)

`Belt::label()` and `Gender::label()` call `Localization::setLocale()` twice per label. Each call clears the message cache, so dropdowns and athlete rows repeatedly include translation files. Either translate in the already-active locale or cache messages per locale without mutating global locale state.

### P-03 — Unbounded and over-broad queries (Medium)

- Club `view=list` loads every athlete and entry without pagination.
- Admin event counts aggregate every entry for every event even though only one event page is displayed.
- `allPublished()` and `nextPublished()` include past events despite “upcoming” naming.
- Public club list is entirely loaded and cached.

Use page-scoped grouped queries, consistent pagination, and date/lifecycle predicates. Measure before adding new caches.

### A-04 and P-04 — Cache/profiler do not justify their complexity

Club and event list caches are never invalidated after create, update, publish, close, or delete, so users see stale data for up to five minutes. The cache serializes PHP objects to mutable files and readers do not take a shared lock. Meanwhile `Database::recordQuery()` is never called, so the debug profiler always has no data. For this project size, removing both mechanisms is simpler and safer until measurements demonstrate a need.

## Architecture and simplicity analysis

### A-01 — Route/feature drift (High)

- Five export PHP stubs and `test_categoria_judo.php` forward to the router, but no routes handle them, so they return 404.
- README still advertises CSV/Excel exports.
- `EventController::entries()` and `views/events/entries.php` exist but are not routed.
- The public “Details” flow uses `show()`, while names and translations still refer to entries in places.
- Several five-line public wrapper files are unnecessary when Apache already rewrites missing paths to `index.php`.

Choose one canonical URL per feature. Restore only required features with authorization tests; remove unsupported stubs and claims rather than maintaining misleading surface area.

### A-02 — Wrong lifecycle for derived judo categories (High)

Athlete edit calculates `program` and `weight_category` using the server's current year (`ClubAreaController.php:61`). Event entry display calculates age class with the event year but reads the stored weight category. An athlete registered for a future/past event can therefore have an age class and weight category derived from different years. Later athlete edits also mutate historical registration output.

Store source facts on the athlete and derive event-specific categories using the event date. If competition records must be historically immutable, snapshot the relevant athlete facts/category on the entry when registration closes. Centralize the definitions so PHP and generated JavaScript cannot drift.

### A-03 — Boundary violations (Medium)

The layout queries the club on every authenticated render. The club list view queries entries in a loop. Controllers contain raw SQL, upload mechanics, repeated auth guards, and repeated error formatting. Move query preparation to models/repositories and pass complete view models to templates. Extract only small, repeated policies (authorization, upload validation, lifecycle checks); avoid a generic service layer with no behavior.

### A-06 — Request/response inconsistency (Medium)

`Router` stores a request at construction, accepts another at dispatch, and injects the stored request into controllers. `LanguageController` does not extend the base controller, reads superglobals directly, emits a header, and exits instead of returning a `Response`. Make the dispatched request the only request object and keep all routes in the response pipeline.

### A-07 — Duplication and dead code (Low)

- Belt colors, text colors, split status, circles, components, and labels encode overlapping representations; the duplication caused the current PHPStan failure.
- Judo weight tables are repeated in calculation and JSON-export methods.
- Navigation path arrays appear in both the helper and layout.
- `Competition::upcoming()` contains past hard-coded 2026 records, and `HomeController` passes them to a view that never uses them.
- `AgeClass::options()`, enum option helpers, and some legacy views/controllers appear unused.

Prefer one data definition with small rendering adapters, then remove confirmed dead code under route/usage tests.

### A-08 — Documentation/configuration drift (Medium)

README says production-ready, all POST forms are CSRF-protected, exports work, and documentation exists under `docs/`; all four claims are false in the audited revision. `.env.example` omits database/admin/debug/mail values, contains unused `APP_AUTO_RUN_MIGRATIONS`, and cannot support the documented quick start. Requirements omit PDO MySQL, `mbstring`, and `fileinfo`, which the code uses.

Documentation should describe verified behavior and link to these root documents.

## Quality and test analysis

### Q-01 — Quality gate is currently red (High)

PHPStan level 6 fails on `Belt::components()`. PHPCS and PHP syntax passed. The stray `</parameter></write_to_file>` text at `views/admin/add_event.php:72-74` is valid non-PHP output, so syntax checks do not catch it.

### Q-02 and Q-03 — Test limitations

The class named `IntegrationTest` constructs controllers directly and has no database or HTTP boundary. There are no tests for club login, password reset, athlete ownership, CSRF rejection, destructive methods, registration transactions, route completeness, unpublished events, upload policy, fresh migrations, or booting a deployment artifact. Tests also rely on the host's session save path.

Every security fix should include a regression test in the same commit. Add a small MySQL service in CI for migration/repository tests and a built-in-server smoke test for routing/artifacts. Coverage percentage is less important than explicit critical-path cases, but a modest changed-code threshold can prevent regression.

### Q-04 — Tooling scope gaps (Medium)

PHPCS excludes views, routes, config, and migrations. PHPStan excludes views, tests, routes, and localization files. There is no HTML/JS validation, route inventory check, or schema contract test. Expand tools selectively; an artifact boot test is more valuable than trying to statically analyze every PHP template at maximum strictness immediately.

## Operational and privacy notes

Uploaded event PDFs are tracked in Git and copied with every artifact. Future runtime uploads in the same directory risk repository growth, accidental disclosure, orphan files after event edits/deletes, and overwrite ambiguity across deployments. Keep user/runtime files in persistent deployment-excluded storage, store only validated relative identifiers, and define retention/deletion behavior. Athlete birth dates, weights, membership numbers, and club contact details are personal data; access logging, backups, retention, and privacy notices should be reviewed outside the code-only audit.

## Recommended target state

Keep the framework-free MVC approach, but enforce these rules:

- One request object and one response path per route.
- Explicit POST actions for every mutation; CSRF and authorization at the action boundary.
- Tenant ownership enforced in the constrained write, not inferred from the UI.
- Source facts stored once; event-specific categories derived from the event year or snapshotted intentionally.
- No database/model calls from views.
- No cache until a measured query warrants it and invalidation is defined.
- Forward-only, fail-closed migrations tested on clean and legacy schemas.
- Deploy the exact tested artifact, preserve server secrets, boot-test it, and health-check after upload.
- README claims only features and controls that route and regression tests verify.
