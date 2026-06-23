# Security, Architecture & Performance Audit

Consolidated findings for the competizionijudo application. Tasks are grouped by priority and dependencies.

---

## How to Use

- Work through phases sequentially (Phase 1 → Phase 4)
- Each phase has a clear target and verification steps
- Commit after completing each phase
- Mark items as `[ ]` (pending), `[/]` (in progress), or `[x]` (done)

---

## Phase 1 – Critical Security (Do First)

**Target:** Eliminate immediate security risks before any feature work.

### [ ] 1. Lock Down Authentication & Authorization

**Files:**
- `src/Controller/AdminController.php`
- `src/Controller/ClubController.php`
- `src/Controller/EventController.php`
- `src/bootstrap.php`

**Consolidates:** Old items 4, 5, 6, 7, 15

**Task:** Harden all auth flows and enforce proper access control.

**Actions:**
1. **Rate-limit login** – Track failed attempts in session; block after 5 failures for 5 minutes.
2. **Secure sessions** – Set `HttpOnly`, `Secure` (when HTTPS), `UseOnlyCookies` in `bootstrap.php`. Regenerate ID after login. Remove duplicate `session_start()` calls.
3. **Password reset hardening** – Invalidate previous tokens before issuing new ones. Clean up used tokens after reset. Fix variable scoping.
4. **Restrict entries visibility** – Ensure non-admin clubs only see their own athletes' entries.
5. **Edit event auth guard** – Verify admin authentication on edit routes, not just redirects.

**Verification:** Test login throttling, session cookie flags, password reset single-use, and data isolation.

---

### [ ] 2. CSRF Protection on All Forms

**Files:**
- `src/helpers.php`
- All controllers with POST handlers
- All view templates with `<form method="post">`

**Consolidates:** Old item 2

**Task:** Prevent cross-site request forgery on every state-changing request.

**Actions:**
1. Add `csrf_token()` and `csrf_field()` helpers to `src/helpers.php`.
2. Validate token at the start of every POST handler.
3. Insert `<?= csrf_field() ?>` in every form.
4. Regenerate token on login/logout.

**Verification:** Submit forms without token; all should be rejected.

---

### [ ] 3. Fix Open Redirect & Add Security Headers

**Files:**
- `src/Controller/LanguageController.php`
- `public/.htaccess`

**Consolidates:** Old items 3, 8

**Task:** Block redirect-based phishing and apply standard security headers.

**Actions:**
1. In language controller, validate `redirect` param against same host only.
2. Add `.htaccess` headers: `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy`. Add HSTS if HTTPS.

**Verification:** Test `?redirect=https://evil.com` → stays on site. Inspect response headers.

---

### [ ] 4. Disable Auto-Migrations & Harden File Uploads

**Files:**
- `src/bootstrap.php`
- `.env.example`
- `src/Controller/AdminController.php`
- `public/uploads/events/.htaccess`

**Consolidates:** Old items 1, 12

**Task:** Remove production migration auto-run and secure file upload endpoints.

**Actions:**
1. Wrap migration runner in `env === 'local'` check in `bootstrap.php`. Remove env var from `.env.example`.
2. Add server-side MIME verification (`finfo_file`) for uploads.
3. Update uploads `.htaccess` to deny PHP execution and set safe MIME types.

**Verification:** Confirm migrations don't run in production mode. Upload PHP renamed as PDF → rejected or plain text.

---

## Phase 2 – High Priority (Before External Testing)

**Target:** Clean input handling and eliminate common web vulnerabilities.

### [ ] 5. Sanitize & Validate All Inputs

**Files:**
- `src/Controller/AdminController.php`
- `src/Controller/ClubController.php`
- `src/Controller/ClubAreaController.php`
- `src/Controller/EventController.php`
- `views/layouts/app.php`

**Consolidates:** Old items 9, 10, 14, 16

**Task:** Replace raw superglobal access with validated, escaped data.

**Actions:**
1. Replace `$_POST`, `$_GET`, `$_SESSION` with `$request->input()` / `$request->query()` where possible.
2. Add simple validators (`validate_email`, `validate_date`) and use them before DB ops.
3. Fix hardcoded year: use `date('Y')` instead of `2026`.
4. Escape `$_GET['view']` with `htmlspecialchars` in layout.

**Verification:** Grep for superglobals in controllers. Submit malformed dates → errors shown. Hardcoded year works across year boundaries.

---

### [ ] 6. Fix Race Condition in Event Registration

**Files:**
- `src/Model/Entry.php`
- `src/Controller/EventController.php`

**Consolidates:** Old item 11

**Task:** Provide clear feedback when duplicate registration is attempted.

**Actions:**
1. Change `INSERT IGNORE` to check `rowCount()` or pre-check with SELECT.
2. Show warning message to user on duplicate attempt.

**Verification:** Register same athlete twice → second attempt shows warning.

---

## Phase 3 – Architecture Improvements (Ongoing)

**Target:** Reduce duplication, improve maintainability, and prepare for growth.

### [ ] 7. Centralize Auth & Navigation Logic

**Files:**
- `src/bootstrap.php`
- `src/Controller/*`
- `views/layouts/app.php`

**Consolidates:** Old items A1, A2, A4, A7, P9

**Task:** Remove repeated auth checks and lazy-load navigation data.

**Actions:**
1. Create session helper class (`src/Core/Session.php`) – start session once, provide `get`/`set`/`regenerate`.
2. Extract submenu building into a reusable component/helper. Cache static submenu items.
3. Only load club data in layout when session exists.

**Verification:** No duplicate `session_start()`. Public pages skip club DB queries.

---

### [ ] 8. Add Basic Caching & Query Optimization

**Files:**
- `src/Model/Club.php`
- `src/Model/Event.php`
- `src/Model/AgeClass.php`
- `migrations/20260619_000000_create_baseline_schema.sql`
- `views/layouts/app.php`

**Consolidates:** Old items P1, P2, P3, P6, P10

**Task:** Reduce database load and eliminate N+1 queries.

**Actions:**
1. Add indexes: `athletes(club_id)`, `entries(event_id, club_id)`, `events(date)`, `events(published, closed, date)`.
2. Batch-calculate categories in `EventController::entries()` instead of per-row.
3. Cache `Club::all()` and `Event::allPublished()` with file cache (5–60 min TTL).
4. Cache localization arrays per request.

**Verification:** `EXPLAIN` shows index usage. DB query count drops on list pages.

---

## Phase 4 – Polish & Future-Proofing

**Target:** Prepare for scaling beyond a few hundred users.

### [ ] 9. Pagination & Asset Optimization

**Files:**
- `src/Controller/AdminController.php`
- `src/Controller/ClubController.php`
- `src/Controller/ClubAreaController.php`
- `public/.htaccess`
- `public/assets/css/app.css`

**Consolidates:** Old items P4, P5, P8

**Task:** Keep memory usage constant and improve repeat-visit speed.

**Actions:**
1. Add pagination (50–100 items/page) to admin club/event lists and athlete lists.
2. Add `.htaccess` caching headers for CSS, JS, images, SVGs (1 month).
3. Enable query profiling in dev (`APP_DEBUG=true`) to catch slow queries.

**Verification:** Insert 1000 records → page still loads fast. Browser dev tools show `Cache-Control` headers.

---

### [ ] 10. Code Quality & Testing Foundation

**Files:**
- `tests/`
- `.github/workflows/` (or GitLab CI)
- `phpstan.neon`, `phpcs.xml`

**Consolidates:** Old items A13, A15, A12

**Task:** Prevent regressions and establish coding standards.

**Actions:**
1. Write unit tests for `helpers.php`, `JudoCategory::calculate()`, `Localization`.
2. Add feature tests for login, registration, event CRUD.
3. Run PHPStan level 5+ and PHP_CodeSniffer (PSR-12) in CI on every PR.

**Verification:** `vendor/bin/phpunit` passes. CI builds green.

---

## Out of Scope (Deferred)

- **A3 Repository pattern** – Low ROI for current team size. Revisit if models grow complex.
- **A5/A6 Validation layer / Immutable value objects** – Simple validators (item 5) are sufficient for now.
- **A8 i18n overhaul** – Current PHP array approach works; switch to gettext/JSON only if adding new languages.
- **A9 Event dispatcher** – No urgent decoupling needs; revisit when side effects multiply.
- **A10 Structured logging** – Add when scaling to multi-server or needing audit trails.
- **A11 Typed config object** – Current array config is acceptable for small apps.
- **A12 Custom error handling** – Current generic handler is adequate; enhance when API is added.
- **A14 Adopt modern framework** – High migration cost; only consider if rewriting major features.
- **P7 Connection pooling** – Not needed for standard PHP; revisit only with long-running workers.

---

*Generated: 2025-06-23*