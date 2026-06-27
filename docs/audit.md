# Security, Architecture & Performance Audit

Consolidated findings for the competizionijudo application. Tasks are grouped by priority and dependencies.

---

## How to Use

- Work through phases sequentially (Phase 1 → Phase 4)
- Each phase has a clear target and verification steps
- All Phase 1-3 items are completed. Phase 4 is partially done.
- Mark items as `[ ]` (pending), `[/]` (in progress), or `[x]` (done)

---

## Phase 1 – Critical Security (Completed)

**Target:** Eliminate immediate security risks before any feature work.

### [x] 1. Lock Down Authentication & Authorization

**Files:**
- `src/Controller/AdminController.php`
- `src/Controller/ClubController.php`
- `src/Controller/EventController.php`
- `src/bootstrap.php`
- `src/Core/Session.php`

**What was done:**
1. **Rate-limit login** – Track failed attempts in session; block after 5 failures for 5 minutes (both admin and club).
2. **Secure sessions** – `HttpOnly` and `Secure` (when HTTPS) cookie params set in `bootstrap.php`. Session ID regenerated after login. `Session` helper class centralizes session handling.
3. **Password reset hardening** – Previous tokens invalidated (set `used=1`) before issuing new ones. Used tokens cleaned up after reset.
4. **Restrict entries visibility** – Non-admin clubs filtered to own athletes only.
5. **Edit event auth guard** – Admin auth verified on edit routes.

---

### [x] 2. CSRF Protection on All Forms

**Files:**
- `src/helpers.php`
- `src/Controller/AdminController.php`
- `src/Controller/ClubController.php`
- `src/Controller/EventController.php`
- View templates with `<form method="post">`

**What was done:**
1. `csrf_token()`, `csrf_field()`, and `validate_csrf()` helpers added to `src/helpers.php`.
2. Token validated at start of every POST handler.
3. `<?= csrf_field() ?>` inserted in every form.
4. Token regenerated on login/logout.

---

### [x] 3. Fix Open Redirect & Add Security Headers

**Files:**
- `src/Controller/LanguageController.php`
- `public/.htaccess`

**What was done:**
1. Language controller validates `redirect` param against allowed paths.
2. `.htaccess` headers added: `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy`, `Strict-Transport-Security`.

---

### [x] 4. Disable Auto-Migrations & Harden File Uploads

**Files:**
- `src/bootstrap.php`
- `src/Controller/AdminController.php`
- `public/uploads/events/.htaccess`

**What was done:**
1. Migration runner wrapped in `env === 'local' || env === 'development'` check in `bootstrap.php`.
2. Server-side MIME verification (`finfo_file`) for uploads.
3. Uploads `.htaccess` denies PHP execution.

---

## Phase 2 – High Priority (Completed)

**Target:** Clean input handling and eliminate common web vulnerabilities.

### [x] 5. Sanitize & Validate All Inputs

**Files:**
- `src/Controller/AdminController.php`
- `src/Controller/ClubController.php`
- `src/Controller/ClubAreaController.php`
- `src/Controller/EventController.php`
- `views/layouts/app.php`

**What was done:**
1. `$_POST`, `$_GET` replaced with `$request->input()` / `$request->query()` where possible.
2. Email validation via `FILTER_VALIDATE_EMAIL`.
3. Hardcoded year partially addressed (see Remaining Issues).
4. `$_GET['view']` escaped with `htmlspecialchars` in layout.

---

### [x] 6. Fix Race Condition in Event Registration

**Files:**
- `src/Model/Entry.php`
- `src/Controller/EventController.php`

**What was done:**
1. Pre-check with SELECT before INSERT.
2. `ALREADY_REGISTERED` exception shows warning to user on duplicate attempt.

---

## Phase 3 – Architecture Improvements (Completed)

**Target:** Reduce duplication, improve maintainability, and prepare for growth.

### [x] 7. Centralize Auth & Navigation Logic

**Files:**
- `src/bootstrap.php`
- `src/Core/Session.php`
- `src/helpers.php` (`build_submenu()`)
- `views/layouts/app.php`

**What was done:**
1. `Session` helper class created (`src/Core/Session.php`) – provides `get`/`set`/`regenerate`/`destroy`.
2. `build_submenu()` helper extracts submenu building logic with static/dynamic items.
3. Club data only loaded in layout when session exists (lazy loading).

---

### [x] 8. Add Basic Caching & Query Optimization

**Files:**
- `src/Model/Club.php`
- `src/Model/Event.php`
- `src/Core/Cache.php`
- `migrations/20260623_000001_add_performance_indexes.sql`

**What was done:**
1. Performance indexes added: `athletes(club_id)`, `entries(event_id, club_id)`, `events(date)`, `events(published, closed, date)`.
2. Category calculation in `EventController::entries()` uses batch approach (per-row grouping).
3. `Club::all()` and `Event::allPublished()` cached with file cache (300s TTL).
4. Localization arrays cached per request via static property in `Localization`.

---

## Phase 4 – Polish & Future-Proofing (In Progress)

**Target:** Prepare for scaling beyond a few hundred users.

### [x] 9. Pagination & Asset Optimization

**Files:**
- `src/Controller/AdminController.php`
- `src/Controller/ClubAreaController.php`
- `src/helpers.php` (`paginate()` function)
- `public/.htaccess`

**What was done:**
1. Pagination added: admin club/event lists (100/page), club athletes (50/page).
2. `.htaccess` caching headers for CSS, JS, images, SVGs (1 month, `immutable`).
3. Query profiling enabled in dev via `APP_DEBUG=true`.

---

### [/] 10. Code Quality & Testing Foundation

**Files:**
- `tests/`
- `.github/workflows/ci.yml`
- `phpstan.neon`, `phpcs.xml`
- `composer.json`

**What was done:**
1. Unit tests exist for: `helpers.php`, `JudoCategory::calculate()`, `Localization`, `Router`.
2. CI runs on push/PR to `main`/`dev` branches with two jobs: quality checks + deploy artifact smoke test.
3. PHPStan level 5 and PHP_CodeSniffer (PSR-12) run in CI.
4. `composer check` runs metadata, syntax, cs, static analysis, tests, and security audit.

**What's missing (see Remaining Issues below):**
- Feature/integration tests for login, registration, event CRUD.
- Coverage for edge cases in existing test classes.

---

## Remaining Issues (Post-Audit Findings)

These issues were discovered during the re-audit and are not fully captured in the original implementation tracking.

| ID | Issue | Severity | File(s) | Description |
|----|-------|----------|---------|-------------|
| R1 | Hardcoded year 2026 in category calculation | Low | `src/helpers.php:59` | `calculateJudoCategory()` defaults to `2026` when no event year provided |
| R2 | Hardcoded year 2026 in athlete age calculation | Low | `src/Model/Athlete.php:64` | `eventYearFromDate()` falls back to `2026` |
| R3 | Hardcoded Fiscal Code in language files | Medium | `lang/en.php:340`, `lang/it.php:340` | `CF 92276860928` exposed in footer translation – should use dynamic data or config |
| R4 | Typo in Italian translation | Low | `lang/it.php:167` | `"Solo agonstico"` should be `"Solo agonistico"` |
| R5 | Missing integration tests | Medium | `tests/` | No feature tests for login, registration, event CRUD flows |
| R6 | Duplicate path in submenu builder | Low | `src/helpers.php:123` | `/event_details.php` appears twice in `$competitionPaths` |
| R7 | Duplicate session_start in csrf_token() | Low | `src/helpers.php:66-68` | `csrf_token()` calls `session_start()` but bootstrap already started the session |

---

## Out of Scope (Deferred)

- **Repository pattern** – Low ROI for current team size. Revisit if models grow complex.
- **Validation layer / Immutable value objects** – Simple validators (item 5) are sufficient for now.
- **i18n overhaul** – Current PHP array approach works; switch to gettext/JSON only if adding new languages.
- **Event dispatcher** – No urgent decoupling needs; revisit when side effects multiply.
- **Structured logging** – Add when scaling to multi-server or needing audit trails.
- **Typed config object** – Current array config is acceptable for small apps.
- **Custom error handling** – Current generic handler is adequate; enhance when API is added.
- **Adopt modern framework** – High migration cost; only consider if rewriting major features.
- **Connection pooling** – Not needed for standard PHP; revisit only with long-running workers.

---

## Codebase Overview Post-Audit

### Architecture
- Framework-free MVC using custom minimal framework primitives (`src/Core/`).
- PSR-4 autoloading via Composer.
- Router dispatches to controller methods based on `routes/web.php`.
- Views are plain PHP templates rendered by `View` class with layout support.
- Models are data-centric DTOs with static factory/query methods.

### Security Posture (Completed)
- ✅ CSRF protection on all forms
- ✅ Session cookie hardening (HttpOnly, Secure, SameSite)
- ✅ Login rate-limiting (5 attempts / 5 min cooldown)
- ✅ Session regeneration after login
- ✅ Password reset token hardening (invalidate previous, single-use)
- ✅ Open redirect protection
- ✅ Security headers (HSTS, X-Content-Type-Options, X-Frame-Options, etc.)
- ✅ File upload MIME validation
- ✅ Auto-migrations disabled in production
- ✅ Prepared statements throughout (no raw SQL injection)
- ⚠️ Remaining: hardcoded fiscal code in lang files (R3)

### Test Coverage
- 4 test files: `HelpersTest`, `JudoCategoryTest`, `LocalizationTest`, `RouterTest`
- Tests cover: HTML escaping, env/config helpers, CSRF token generation, pagination, Judo category calculation, localization, route dispatching
- **Missing:** Integration tests for login, registration, event CRUD, athlete management

### CI/CD
- GitHub Actions CI: quality checks (PHP 8.2 + 8.4 matrix) + deploy artifact smoke test
- GitHub Actions Deploy: FTP deployment to production (`main` → `prod/`) and development (`dev` → `dev/`)
- Git hooks for pre-commit (syntax check) and pre-push (full CI)

---

*Generated: 2026-06-26 | Last audit: 2026-06-26*