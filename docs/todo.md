# Current Actionable Items (Post-Audit)

## Medium Priority

1. **Remove hardcoded Fiscal Code from language files**
   - Files: `lang/en.php:340`, `lang/it.php:340`
   - Replace literal Fiscal Code with a dynamic placeholder or config-driven value

2. **Add integration tests**
   - Files: `tests/`
   - Cover: admin login, club login, event registration, athlete CRUD

## Low Priority

3. **Fix hardcoded year 2026 in `calculateJudoCategory()`**
   - File: `src/helpers.php:59`
   - Use `date('Y')` or pass event year dynamically

4. **Fix hardcoded year 2026 in `Athlete::eventYearFromDate()`**
   - File: `src/Model/Athlete.php:64`
   - Use `date('Y')` instead of hardcoded value

5. **Fix typo in Italian translation**
   - File: `lang/it.php:167`
   - "Solo agonstico" → "Solo agonistico"

6. **Remove duplicate path in submenu builder**
   - File: `src/helpers.php:123`
   - Remove the duplicate `/event_details.php` entry

7. **Fix duplicate `session_start()` in `csrf_token()` helper**
   - File: `src/helpers.php:66-68`
   - Use `Session::start()` instead of raw `session_start()`

## Completed (Phase 1-3, Phase 4 Task 9)

All security hardening, input validation, architecture improvements, caching, pagination, and static asset optimization are implemented and verified.