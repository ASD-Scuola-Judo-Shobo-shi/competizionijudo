# Codebase Security Audit & Remediation Guide

Consolidated findings from the security and code quality analysis of the competizionijudo application.

---

## How to Use This Document

Each section is organized by severity (Critical → Low) and contains:
- **Task**: The specific issue to address
- **Files**: Exact files requiring changes
- **Action**: Concrete steps to fix
- **Verification**: How to confirm the fix works

Progress should be tracked by marking items as completed.

---

## CRITICAL Issues

### [ ] 1. Remove Auto-Migration Capability from Production
**Files:**
- `src/bootstrap.php` (lines 38-41)
- `.env.example` (line 13)

**Task:** Eliminate the ability to run migrations automatically in production.

**Action:**
1. In `src/bootstrap.php`, wrap the auto-migration block in a development-only check:
   ```php
   if (config('app.env') === 'local' && in_array(strtolower((string) env('APP_AUTO_RUN_MIGRATIONS', 'false')), ['1', 'true', 'yes'], true)) {
       $pdo = App\Model\Database::connection();
       (new App\Model\MigrationRunner($pdo))->run();
   }
   ```
2. Remove `APP_AUTO_RUN_MIGRATIONS` from `.env.example`.
3. Ensure `config/app.php` contains `'env' => env('APP_ENV', 'production')` (already present).

**Verification:** Confirm `APP_AUTO_RUN_MIGRATIONS` has no effect when `APP_ENV=production`.

---

### [ ] 2. Implement CSRF Protection on All Forms
**Files:**
- All controller POST handlers:
  - `src/Controller/AdminController.php`
  - `src/Controller/EventController.php`
  - `src/Controller/ClubController.php`
  - `src/Controller/ClubAreaController.php`
  - `src/Controller/LanguageController.php`
- All view templates with `<form method="post">`:
  - `views/admin/add_event.php`
  - `views/events/register.php`
  - `views/club/login.php`
  - `views/club/register.php`
  - `views/club/forgot_password.php`
  - `views/club/reset_password.php`
  - `views/layouts/app.php` (language switch form)

**Task:** Add CSRF token generation, storage, and validation.

**Action:**
1. In `src/helpers.php`, add:
   ```php
   function csrf_token(): string {
       if (empty($_SESSION['csrf_token'])) {
           $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
       }
       return $_SESSION['csrf_token'];
   }
   
   function csrf_field(): string {
       return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
   }
   ```
2. In each controller POST handler, add at the start:
   ```php
   $csrfToken = (string) ($request->input('csrf_token') ?? '');
   if ($csrfToken === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
       return $this->redirect('/');
   }
   ```
3. In each view form, add `<?= csrf_field() ?>` immediately after the opening `<form>` tag.
4. In `AdminController::logout()` and `ClubController::logout()`, regenerate CSRF token on login.

**Verification:** Submit forms without CSRF token; all should be rejected.

---

### [ ] 3. Fix Open Redirect in Language Controller
**Files:**
- `src/Controller/LanguageController.php` (lines 24-25)

**Task:** Prevent redirecting users to arbitrary external domains after language switch.

**Action:**
Replace the redirect logic with:
```php
$referer = $_SERVER['HTTP_REFERER'] ?? '/';
$parsed = parse_url($referer);
$host = $parsed['host'] ?? '';
$allowedHost = parse_url((string) env('APP_URL', 'http://localhost:8080'), PHP_URL_HOST);

if ($host === '' || $host === $allowedHost) {
    header('Location: ' . $referer);
} else {
    header('Location: /');
}
exit;
```

**Verification:** Test that `?locale=en&redirect=https://evil.com` redirects to `/`, not external site.

---

## HIGH Severity Issues

### [ ] 4. Add Brute-Force Protection to Authentication Endpoints
**Files:**
- `src/Controller/AdminController.php` (login method)
- `src/Controller/ClubController.php` (login method)

**Task:** Implement rate limiting for login attempts.

**Action:**
1. Create `src/Model/LoginAttempt.php` or add simple tracking to `Club.php`/new table `login_attempts`.
2. Alternative lightweight fix: Store failed attempt count in session:
   ```php
   if (!empty($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 5) {
       $errors[] = __('Too many attempts. Try again in 5 minutes.');
       // Check elapsed time since first attempt
       if (time() - ($_SESSION['first_attempt_time'] ?? time()) < 300) {
           return $this->view(...);
       }
       unset($_SESSION['login_attempts']);
   }
   if ($loginFailed) {
       $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
       $_SESSION['first_attempt_time'] = $_SESSION['first_attempt_time'] ?? time();
   }
   ```

**Verification:** After 5 failed logins, verify subsequent attempts are blocked for 5 minutes.

---

### [ ] 5. Standardize Session Security
**Files:**
- `src/Controller/AdminController.php`
- `src/Controller/ClubController.php`
- `src/bootstrap.php`

**Task:** Ensure consistent session security across all auth flows.

**Action:**
1. In `src/bootstrap.php`, set secure session parameters at the start:
   ```php
   if (session_status() === PHP_SESSION_NONE) {
       ini_set('session.cookie_httponly', '1');
       ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
       ini_set('session.use_only_cookies', '1');
       session_start();
   }
   ```
2. In `ClubController::login()`, add `session_regenerate_id(true)` after successful auth.
3. In `ClubController::register()`, auto-login the club after registration (optional UX improvement).
4. Remove redundant `session_start()` calls from controller methods (they should only happen in bootstrap).

**Verification:** Verify `PHPSESSID` cookie has `HttpOnly` flag set.

---

### [ ] 6. Restrict Event Entries Visibility for Non-Admins
**Files:**
- `src/Controller/EventController.php` (entries method, lines 123-188)

**Task:** Ensure clubs can only see their own athletes' entries unless they are admin.

**Action:**
In the `entries()` method, modify the query or filtering logic:
```php
if (!$isAdmin && $clubId !== null) {
    $clubFilter = $clubId; // Force club filter for non-admin users
}
```
Alternatively, modify `Entry::findByEvent()` to always accept a club filter and enforce it here.

**Verification:** Log in as a non-admin club and verify only that club's athletes appear.

---

### [ ] 7. Harden Password Reset Token Management
**Files:**
- `src/Controller/ClubController.php` (forgotPassword and resetPassword methods)

**Task:** Prevent token accumulation and ensure single-use semantics.

**Action:**
1. In `forgotPassword()`, invalidate previous tokens before inserting new one:
   ```php
   $stmt = Database::connection()->prepare('UPDATE password_reset_tokens SET used = 1 WHERE club_id = ?');
   $stmt->execute([$club->id]);
   ```
2. In `resetPassword()`, after successful password change, also delete used tokens for that club:
   ```php
   $stmt = Database::connection()->prepare('DELETE FROM password_reset_tokens WHERE club_id = ? AND used = 1');
   $stmt->execute([$clubId]);
   ```
3. Fix variable scoping bug: Ensure `$email` is always defined before view render.

**Verification:** Request two password resets; verify only the latest token works.

---

### [ ] 8. Add Security Headers
**Files:**
- `public/.htaccess`
- `src/Core/Response.php` (check if exists, add middleware)

**Task:** Apply standard web security headers.

**Action:**
Add to `public/.htaccess`:
```apache
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options SAMEORIGIN
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
```
For HSTS, if site uses HTTPS, add:
```apache
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

**Verification:** Inspect response headers in browser dev tools.

---

## MEDIUM Severity Issues

### [ ] 9. Replace Raw Superglobal Access with Request Object
**Files:**
- `src/Controller/AdminController.php`
- `src/Controller/ClubController.php`
- `src/Controller/ClubAreaController.php`
- `src/Controller/EventController.php`
- `views/layouts/app.php`

**Task:** Standardize input access through the Request abstraction.

**Action:**
Replace patterns like:
- `$_POST['field']` → `$request->input('field')`
- `$_GET['field']` → `$request->query('field')`
- `$_SESSION['key']` → inject session helper or use request wrapper

**Verification:** Grep for `$_POST`, `$_GET`, `$_SESSION` in src/Controller; should be minimal or zero.

---

### [ ] 10. Add Input Validation Rules
**Files:**
- `src/Controller/AdminController.php`
- `src/Controller/ClubController.php`
- `src/Controller/ClubAreaController.php`
- `src/Controller/EventController.php`

**Task:** Validate all user inputs before processing.

**Action:**
1. Create simple validator helper in `src/helpers.php`:
   ```php
   function validate_email(string $email): bool { return filter_var($email, FILTER_VALIDATE_EMAIL) !== false; }
   function validate_date(string $date): bool { return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1; }
   ```
2. Apply in controllers before DB operations. Reject invalid dates in event creation.

**Verification:** Submit forms with malformed dates; errors should display.

---

### [ ] 11. Fix Race Condition in Event Registration
**Files:**
- `src/Model/Entry.php` (register method)
- `src/Controller/EventController.php` (register method)

**Task:** Provide feedback when duplicate registration is attempted.

**Action:**
1. Change `INSERT IGNORE` to `INSERT ... ON DUPLICATE KEY UPDATE id=id` or check affected rows:
   ```php
   $stmt = Database::connection()->prepare(
       'INSERT IGNORE INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
   );
   $stmt->execute([$eventId, $clubId, $athleteId]);
   if ($stmt->rowCount() === 0) {
       // Duplicate - add error message to session flash or return via redirect
   }
   ```
2. Alternatively, pre-check with SELECT before INSERT to avoid silent failure.

**Verification:** Try registering same athlete twice; second attempt should show warning.

---

### [ ] 12. Secure File Upload Handling
**Files:**
- `src/Controller/AdminController.php` (addEvent method)
- `public/uploads/events/.htaccess`

**Task:** Prevent execution of uploaded files and verify MIME types server-side.

**Action:**
1. Add to `.htaccess` in uploads directory:
   ```apache
   <FilesMatch "\.(php|php[345]?|phtml)$">
       Order Allow,Deny
       Deny from all
   </FilesMatch>
   AddType text/plain .pdf .jpg .jpeg .png
   ```
2. In admin controller, add MIME verification:
   ```php
   $finfo = finfo_open(FILEINFO_MIME_TYPE);
   $mime = finfo_file($finfo, $_FILES['poster_file']['tmp_name']);
   $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
   if (!in_array($mime, $allowedMimes, true)) {
       throw new \Exception('Invalid file type');
   }
   ```

**Verification:** Attempt to upload PHP file renamed to .pdf; should be rejected or rendered as plain text.

---

## LOW Severity / Code Quality Issues

### [ ] 13. Remove Unused View Parameters
**Files:**
- `src/Controller/EventController.php` (show method)
- `views/events/show.php`

**Task:** Clean up unused variables passed to views.

**Action:**
In `EventController::show()`, remove:
```php
'upcomingEvents' => [],
```
And in `index()` if not needed. Verify view doesn't depend on it.

**Verification:** Run PHPStan or PHP_CodeSniffer if configured; no undefined variable warnings.

---

### [ ] 14. Fix Hardcoded Year in Category Calculation
**Files:**
- `src/Controller/EventController.php` (entries method, line 160)

**Task:** Use dynamic year instead of hardcoded 2026.

**Action:**
Replace:
```php
$eventYear = $eventDate !== '' ? (int) substr($eventDate, 0, 4) : 2026;
```
With:
```php
$eventYear = $eventDate !== '' ? (int) substr($eventDate, 0, 4) : (int) date('Y');
```

**Verification:** Run tests (if any) or verify calculation logic in UI.

---

### [ ] 15. Add Authorization Guard to Edit Event Route
**Files:**
- `src/Controller/AdminController.php` (editEvent method)
- `routes/web.php`

**Task:** Ensure `admin_edit_event.php` checks admin auth, not just redirects.

**Action:**
Modify `editEvent()`:
```php
public function editEvent(Request $request): Response
{
    session_start();
    if (empty($_SESSION['is_admin'])) {
        return $this->redirect('/admin_login.php');
    }
    // ... rest
}
```

**Verification:** Access `/admin_edit_event.php?id=1` without being logged in; should redirect to login.

---

### [ ] 16. Sanitize View Data in Layout
**Files:**
- `views/layouts/app.php` (line 100)

**Task:** Escape user-controlled input used in view logic.

**Action:**
Change:
```php
$clubView = (string) ($_GET['view'] ?? '');
```
To:
```php
$clubView = htmlspecialchars((string) ($_GET['view'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
```

**Verification:** Inject script in ?view parameter; should not execute.

---

## Verification Checklist

After completing all tasks:
- [ ] Run `composer install` and `make test` (if available)
- [ ] Run PHPStan: `vendor/bin/phpstan analyse`
- [ ] Run CS check: `vendor/bin/phpcs`
- [ ] Review `config/app.php` to confirm `APP_DEBUG=false` for production
- [ ] Verify `.env` file is not committed to version control
- [ ] Test all auth flows manually:
  - Club registration/login/logout
  - Admin login/logout
  - Password reset flow
  - Event registration

---

## Notes

- This is a **custom PHP framework** (no Composer dependencies for the framework itself).
- Sessions are started in multiple places; consolidate into `src/bootstrap.php`.
- The `e()` helper correctly uses `htmlspecialchars` with `ENT_QUOTES`.
- Database access uses PDO prepared statements (good), avoiding SQL injection in most places.
- File upload directory is web-accessible; consider moving outside document root or restricting execution.

---

## Architecture Improvement Recommendations

The codebase uses a basic custom MVC framework. While functional, several architectural patterns could be improved for better maintainability, testability, and scalability.

---

### [ ] A1. Separate Legacy Entry Points from Modern MVC

**Current State:** Routes are defined in `routes/web.php`, but many public endpoints (`public/*.php`) directly `require 'index.php'` and bypass the router.

**Recommendation:**
- Migrate all legacy entry points to use the router (e.g., `public/admin.php` → `routes/web.php`).
- Alternatively, if keeping legacy files, extract shared logic into middleware/base controllers to avoid duplication (e.g., auth checks).

**Benefit:** Single source of truth for routing; easier to audit and secure endpoints.

---

### [ ] A2. Introduce a Middleware Pipeline

**Current State:** Auth checks are repeated in every controller method (`session_start(); if (!isAdmin) redirect...`).

**Recommendation:**
- Implement a middleware stack in `src/Core/Application.php` or `src/Core/Router.php`.
- Create middleware classes: `AuthMiddleware`, `CsrfMiddleware`, `RateLimitMiddleware`.
- Apply middleware per route group:
  ```php
  $router->group('/admin', middleware: [AuthMiddleware::class, CsrfMiddleware::class], function ($router) {
      $router->get('/manage_events', [AdminController::class, 'manageEvents']);
  });
  ```

**Benefit:** DRY auth/validation logic; single enforcement point; easier to add cross-cutting concerns.

---

### [ ] A3. Replace Static Model Methods with Repository Pattern

**Current State:** Models are anemic data holders with static methods (`Club::findById()`, `Event::allPublished()`). This makes testing, mocking, and separation of concerns difficult.

**Recommendation:**
- Create `src/Repository/ClubRepository.php`, `src/Repository/EventRepository.php`, etc.
- Models become pure value objects (DTOs) with no persistence logic.
- Repositories handle queries, mapping results to model objects.
- Example:
  ```php
  class ClubRepository {
      public function findById(int $id): ?Club { ... }
      public function save(Club $club): void { ... }
  }
  ```

**Benefit:** Better testability (mock repositories); separation of persistence vs. domain; easier to switch database implementations.

---

### [ ] A4. Centralize Session Management

**Current State:** `session_start()` is called in `src/bootstrap.php`, `AdminController`, `ClubController`, `ClubAreaController`. Risk of "headers already sent" and inconsistent session config.

**Recommendation:**
- Create `src/Core/Session.php` class to encapsulate session operations.
- Start session only once in `src/bootstrap.php`.
- Use dependency injection or static facade for session access.
- Example:
  ```php
  class Session {
      public static function get(string $key, mixed $default = null): mixed { ... }
      public static function set(string $key, mixed $value): void { ... }
      public static function regenerate(bool $delete = false): void { ... }
  }
  ```

**Benefit:** Single source of truth for session state; easier to swap to Redis/file sessions later.

---

### [ ] A5. Add Request Validation Layer

**Current State:** Controllers manually validate inputs via loose `if` checks and superglobal access.

**Recommendation:**
- Implement a validation library (e.g., `Respect/Validation`) or custom `Validator` class.
- Use Form Request objects or dedicated validators:
  ```php
  class CreateEventRequest {
      public function rules(): array {
          return [
              'name' => 'required|string|max:255',
              'date' => 'required|date',
              'location' => 'required|string|max:255',
          ];
      }
  }
  ```
- In controller:
  ```php
  $validator = new CreateEventRequest($request);
  if ($validator->fails()) {
      return $this->view('admin/add_event', ['errors' => $validator->errors()]);
  }
  ```

**Benefit:** Centralized validation logic; automatic XSS prevention via strict typing; clearer error messages.

---

### [ ] A6. Replace Direct Superglobal Access with Immutable Value Objects

**Recommendation:**
- `Request` and `Response` are good starts, but extend `Request` to provide typed getters for all expected inputs.
- Remove `$_POST`, `$_GET`, `$_SESSION` access from controllers entirely.
- For session, either extend `Request` wrapper or use dedicated `Session` service.

**Benefit:** Immutable request objects are thread-safe, cacheable, and trivially testable.

---

### [ ] A7. Adopt a Component-Based View Layer

**Current State:** Views are PHP templates with scattered logic. `views/layouts/app.php` contains ~100 lines of PHP for navigation.

**Recommendation:**
- Create reusable view components for navigation, submenu, language switcher, etc.
- Example `src/View/Components/Nav.php` or partial templates:
  ```php
  // views/components/submenu.php
  <?php foreach ($items as $item): ?>
      <a href="<?= e($item['url']) ?>" class="submenu-item"><?= e($item['label']) ?></a>
  <?php endforeach; ?>
  ```
- Use a `render()` helper or View Composer pattern.

**Benefit:** DRY templates; easier to test UI logic; non-PHP developers can edit simple templates.

---

### [ ] A8. Separate Translation/i18n Concerns

**Current State:** `src/Localization.php` is a simple class loaded from PHP arrays. No pluralization, no fallback, no context.

**Recommendation:**
- Move to a standard i18n format (gettext `.po/.mo` or JSON translations).
- Implement translation fallback (e.g., `en` fallback for missing `it` strings).
- Example with Symfony Translation component (if using Composer):
  ```php
  $translator = new Translator('it');
  $translator->addLoader('php', new ArrayFileLoader());
  $translator->addResource('php', lang/it.php, 'it');
  ```

**Benefit:** Professional localization workflow; easier for translators; proper pluralization support.

---

### [ ] A9. Introduce an Event Dispatcher / Observer Pattern

**Current State:** Tight coupling between controllers and side effects (e.g., after registration, send email; after event creation, notify clubs).

**Recommendation:**
- Create `src/Event/EventDispatcher.php` and domain events:
  ```php
  class ClubRegistered {
      public function __construct(public Club $club) {}
  }
  ```
- In controller:
  ```php
  event(new ClubRegistered($club));
  ```
- Listeners handle email, logs, notifications.

**Benefit:** Decoupled side effects; easy to add new behaviors without modifying controllers.

---

### [ ] A10. Add Structured Logging

**Current State:** No logging framework. Error handling in controllers either shows user-friendly messages or raw stack traces (if debug).

**Recommendation:**
- Integrate PSR-3 logger (e.g., Monolog via Composer).
- Log auth failures, password reset requests, errors.
- Example:
  ```php
  $this->logger->warning('Failed login attempt', ['email' => $email, 'ip' => $_SERVER['REMOTE_ADDR']]);
  ```

**Benefit:** Audit trail; faster debugging; compliance evidence.

---

### [ ] A11. Move Configuration to a Typed Config Object

**Current State:** `config/app.php` returns an array. Access via `config('app.events_upcoming_limit')` with magic strings.

**Recommendation:**
- Create `src/Config/AppConfig.php` with typed properties:
  ```php
  final class AppConfig {
      public function __construct(
          public string $name,
          public string $env,
          public bool $debug,
          public string $url,
          public int $eventsUpcomingLimit,
      ) {}
  }
  ```
- Bind config to container or inject where needed.

**Benefit:** IDE autocompletion; compile-time safety; avoid typos in config keys.

---

### [ ] A12. Implement a Proper Error Handling Strategy

**Current State:** `Application::handle()` catches `Throwable` and either shows a debug page or a generic 500.

**Recommendation:**
- Create custom exception classes: `ValidationException`, `NotFoundException`, `UnauthorizedException`, `ConflictException`.
- Map exceptions to HTTP status codes in a dedicated `ExceptionHandler`.
- Render appropriate error views (`errors/404.php`, `errors/500.php`).
- Always log exceptions; never show them in production.

**Benefit:** Consistent error responses; easier debugging; better UX.

---

### [ ] A13. Add Automated Tests and CI

**Current State:** `tests/` contains one `RouterTest.php`. Most logic untested.

**Recommendation:**
- Set up PHPUnit (already has `phpunit.xml`).
- Write unit tests for: `helpers.php` functions, `JudoCategory::calculate()`, `Localization`.
- Write feature tests for: login flows, event registration, admin CRUD.
- Add GitHub Actions or GitLab CI for automated testing on PRs.

**Benefit:** Prevent regressions; enable safe refactoring; documentation via executable examples.

---

### [ ] A14. Consider Adopting a Modern PHP Framework

**Current State:** Custom framework with ~2k LOC core. Requires maintaining routing, session, validation, CSRF, security headers, etc.

**Recommendation:**
- Evaluate frameworks like Laravel, Symfony, or Slim.
- Even gradual migration (micro-framework like Slim for new endpoints) reduces long-term maintenance.
- If staying custom, extract to a private Composer package with proper versioning.

**Benefit:** Battle-tested security; ecosystem (ORM, mail, queues); faster feature development.

---

### [ ] A15. Establish Coding Standards and Static Analysis

**Current State:** `phpcs.xml` and `phpstan.neon` exist but may not be enforced.

**Recommendation:**
- Run PHPStan level 5+ in CI; fix type errors.
- Use PHP_CodeSniffer with PSR-12 or PER Coding Style.
- Add pre-commit hooks (`.git/hooks/pre-commit`) to run quick checks.

**Benefit:** Consistent codebase; fewer bugs; smoother onboarding.

---

## Performance Audit & Optimization Recommendations

The application is functional but has several performance bottlenecks that will degrade user experience as data volume grows.

---

### [ ] P1. Add Missing Database Indexes

**Current State:** Schema has no indexes on foreign keys or frequently filtered/sorted columns.

**Files:**
- `migrations/20260619_000000_create_baseline_schema.sql`

**Task:** Add indexes to speed up JOINs and lookups.

**Action:**
1. Add indexes on foreign key columns:
   ```sql
   CREATE INDEX idx_athletes_club_id ON athletes(club_id);
   CREATE INDEX idx_entries_event_id ON entries(event_id);
   CREATE INDEX idx_entries_club_id ON entries(club_id);
   CREATE INDEX idx_entries_athlete_id ON entries(athlete_id);
   ```
2. Add composite index for common filter in entries view:
   ```sql
   CREATE INDEX idx_entries_event_club ON entries(event_id, club_id);
   ```
3. Add index on `events.date` for upcoming events query:
   ```sql
   CREATE INDEX idx_events_date ON events(date);
   CREATE INDEX idx_events_published_closed_date ON events(published, closed, date);
   ```
4. Add index on `events.location` if location search is frequent:
   ```sql
   CREATE INDEX idx_events_location ON events(location);
   ```

**Impact:** Reduces query time from O(n) to O(log n) on filtered lookups. Critical when events/athletes exceed 1000 rows.

**Verification:** Run `EXPLAIN` on slow queries before and after indexing.

---

### [ ] P2. Eliminate N+1 Query Patterns

**Current State:** Several controller loops trigger database queries per iteration.

**Files:**
- `src/Controller/EventController.php` (entries method, lines 156-177)
- `src/Controller/ClubAreaController.php` (index method, lines 100-110)
- `views/layouts/app.php` (lines 103-108)

**Task:** Batch load related data instead of querying per row.

**Action:**
1. In `EventController::entries()`, batch-fetch categories instead of calculating inline:
   ```php
   // Pre-calculate all categories for the rows
   $categories = [];
   foreach ($rows as $row) {
       $birthYear = JudoCategory::extractBirthYear($row['birth_date'] ?? '');
       $eventYear = $eventDate !== '' ? (int) substr($eventDate, 0, 4) : (int) date('Y');
       if ($birthYear !== null) {
           $acResult = AgeClass::calculate($birthYear, $eventYear);
           $categories[$row['entry_id']] = $acResult['label'];
       }
   }
   ```
2. In `ClubAreaController::index()`, avoid rebuilding `$competitions` array from `$allEntries`:
   ```php
   $competitions = Entry::findCompetitionsByClub($club->id); // New repository method
   ```
3. In `views/layouts/app.php`, cache club email lookup:
   ```php
   static $clubCache = null;
   if ($clubCache === null && $isLoggedIn) {
       $clubCache = \App\Model\Club::findById((int) $_SESSION['club_id']);
   }
   $clubEmail = $clubCache?->email;
   ```

**Impact:** Reduces queries from O(n) to O(1) for listing pages.

---

### [ ] P3. Implement Query Result Caching

**Current State:** `Club::all()`, `AgeClass` lookups, and navigation data are reloaded from DB on every request.

**Files:**
- `src/Model/Club.php` (all method)
- `src/Model/AgeClass.php`
- `views/layouts/app.php`

**Task:** Cache infrequently changing data in memory or file cache.

**Action:**
1. Create `src/Cache/FileCache.php`:
   ```php
   final class FileCache {
       public static function get(string $key, int $ttl = 300): mixed {
           $path = base_path('var/cache/') . md5($key);
           if (!is_file($path) || (time() - filemtime($path)) > $ttl) {
               return null;
           }
           return unserialize(file_get_contents($path));
       }
       public static function set(string $key, mixed $value, int $ttl = 300): void {
           $path = base_path('var/cache/') . md5($key);
           file_put_contents($path, serialize($value));
       }
   }
   ```
2. Wrap expensive calls:
   ```php
   $clubs = FileCache::get('clubs_all', 3600);
   if ($clubs === null) {
       $clubs = Club::all();
       FileCache::set('clubs_all', $clubs, 3600);
   }
   ```
3. Invalidate cache on club CRUD operations.

**Impact:** Reduces DB load for high-traffic pages (homepage, navigation).

**Verification:** Check `var/cache/` directory for cached files; monitor DB query count.

---

### [ ] P4. Add Pagination to List Views

**Current State:** `Club::all()`, `Event::allPublished()`, and athlete lists load all records into memory.

**Files:**
- `src/Controller/AdminController.php` (manageClubs, manageEvents)
- `src/Controller/ClubAreaController.php` (index)
- `src/Controller/ClubController.php` (list)

**Task:** Paginate large datasets to reduce memory usage and response time.

**Action:**
1. Modify repository/list methods to accept `limit` and `offset`:
   ```php
   public static function paginate(int $page = 1, int $perPage = 50): array {
       $offset = ($page - 1) * $perPage;
       $stmt = Database::connection()->prepare('SELECT * FROM athletes ORDER BY last_name LIMIT ? OFFSET ?');
       $stmt->execute([$perPage, $offset]);
       return array_map(fn($r) => self::fromArray($r), $stmt->fetchAll());
   }
   ```
2. Update views to show pagination links.
3. Default to 50–100 items per page for admin views.

**Impact:** Keeps memory usage constant as data grows; faster page loads.

**Verification:** Insert 1000 clubs; verify page loads quickly and shows pagination controls.

---

### [ ] P5. Optimize Static Asset Delivery

**Current State:** CSS (`app.css`) is served without compression or caching headers. No build process.

**Files:**
- `public/assets/css/app.css`
- `public/.htaccess`

**Task:** Reduce asset size and leverage browser caching.

**Action:**
1. Minify CSS (use `csso` or `cleancss`):
   ```bash
   npm install -g csso-cli
   csso public/assets/css/app.css public/assets/css/app.min.css
   ```
2. Update `.htaccess` for caching:
   ```apache
   <IfModule mod_expires.c>
       ExpiresActive On
       ExpiresByType text/css "access plus 1 month"
       ExpiresByType image/svg+xml "access plus 1 month"
       ExpiresByType image/jpeg "access plus 1 month"
       ExpiresByType image/png "access plus 1 month"
   </IfModule>
   <IfModule mod_headers.c>
       <FilesMatch "\.(css|js|png|jpg|jpeg|svg|woff2?)$">
           Header set Cache-Control "public, max-age=2592000"
       </FilesMatch>
   </IfModule>
   ```
3. Consider bundling multiple CSS files if needed.

**Impact:** Faster repeat visits; reduced bandwidth.

**Verification:** Use Chrome DevTools Network tab; verify `Cache-Control` headers and `transferSize`.

---

### [ ] P6. Reduce Per-Request Bootstrap Overhead

**Current State:** `config/*.php` files are loaded from disk on every request. `lang/*.php` arrays are loaded per request. Session is always started.

**Files:**
- `src/helpers.php` (config function)
- `src/bootstrap.php`
- `src/Localization.php`

**Task:** Cache configuration and translation loading.

**Action:**
1. In `config()` helper, the caching is already there (static `$items`). Verify it works and doesn't reload.
2. In `Localization.php`, cache loaded translation arrays:
   ```php
   private static ?array $translations = null;
   public static function trans(string $key, array $replacements = []): string {
       if (self::$translations === null) {
           $locale = self::getLocale();
           $file = base_path('lang/' . $locale . '.php');
           self::$translations = is_file($file) ? require $file : [];
       }
       // ...
   }
   ```
3. Only start session when needed (auth endpoints, forms). Use session-less CSRF alternative for GET routes or accept risk for low-stakes actions.

**Impact:** Reduces file I/O from ~10 to ~2 per request.

**Verification:** Profile with `microtime(true)` around config/lang loading; should be near-zero after first request.

---

### [ ] P7. Avoid Repeated Database Connections in Loops

**Current State:** `Database::connection()` uses a singleton, but models call `Database::connection()` repeatedly.

**Files:**
- `src/Model/Club.php` (add, all, etc.)
- `src/Model/Athlete.php`
- `src/Model/Event.php`

**Task:** Ensure single connection reuse across requests.

**Action:**
The current singleton pattern is correct. Verify it's not being reset inadvertently. If using long-running workers (e.g., ReactPHP), add connection pooling.

**Impact:** Negligible for standard PHP, but important for CLI/tests.

**Verification:** Add logger to `Database::connection()`; count invocations per request.

---

### [ ] P8. Add Database Query Profiling in Development

**Current State:** No tooling to identify slow queries during development.

**Files:**
- `src/Model/Database.php`

**Task:** Enable query logging in non-production environments.

**Action:**
1. Add to `Database::connection()` when `APP_DEBUG=true`:
   ```php
   if (config('app.debug', false)) {
       $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [DebugStatement::class]);
   }
   ```
2. Create `src/Model/DebugStatement.php`:
   ```php
   class DebugStatement extends PDOStatement {
       public function execute($params = null): bool {
           $start = microtime(true);
           $result = parent::execute($params);
           $time = microtime(true) - $start;
           if ($time > 0.01) { // Log slow queries >10ms
               error_log(sprintf('Slow query (%0.2fms): %s', $time * 1000, $this->queryString));
           }
           return $result;
       }
   }
   ```

**Impact:** Surface N+1 and missing indexes proactively.

---

### [ ] P9. Lazy-Load Navigation Data

**Current State:** `views/layouts/app.php` loads club data and builds submenu arrays on every page, even public pages that don't need it.

**Files:**
- `views/layouts/app.php` (lines 98-141)

**Task:** Only compute navigation/authentication UI when necessary.

**Action:**
1. Move club email lookup into an `include` or component that's only loaded when session exists.
2. Cache submenu items per path (they're static) using the FileCache from P3.

**Impact:** Saves ~1-2 DB queries on public pages (homepage, events list).

---

### [ ] P10. Consolidate Redundant Queries

**Current State:** `Club::all()` is called multiple times in admin controllers and views.

**Files:**
- `src/Controller/AdminController.php`
- `views/admin/manage_clubs.php`

**Task:** Reuse club list across requests via caching (see P3) or pass data between views via controller.

**Action:**
Apply cache wrapper from P3 to `Club::all()` and `Event::allPublished()`.

---

## General Recommendations

- **Consider read replicas** if the application grows beyond a few hundred concurrent users.
- **Use a real cache store** (Redis/Memcached) instead of file cache for shared hosting with multiple workers.
- **Profile with Xdebug** or `blackfire` to identify actual bottlenecks before premature optimization.
- **Enable MySQL slow query log** to catch regressions.

---

*Generated: 2025-06-23*
</content>
<task_progress>
- [x] Document security audit findings
- [x] Analyze architecture issues
- [x] Add architecture improvement recommendations to document
</task_progress>
