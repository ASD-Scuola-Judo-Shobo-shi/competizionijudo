# AGENTS.md

This file defines the working agreement for coding agents in this repository.
It applies to the entire tree unless a more specific `AGENTS.md` exists below
the file being changed.

## Project context

Competizioni Judo is a framework-free PHP 8.4 MVC application. Production uses
MySQL; tests use PHPUnit and often exercise the application with synthetic
SQLite fixtures. `public/index.php` is the front controller, and
`routes/web.php` is the source of truth for routes and access gates.

Keep the application explicit and small. Do not introduce a framework,
dependency container, ORM, frontend build system, or generic abstraction unless
the requested change clearly requires it.

## Before editing

- Inspect `git status --short --branch`, the recent log, and relevant diffs.
- Treat all existing working-tree changes as user-owned. Do not discard,
  overwrite, or reformat unrelated work.
- Read the relevant controller, model/service, view, route, translation, and
  tests before deciding where a change belongs.
- Confirm the reported behavior still exists in the current code.
- For remediation-roadmap work explicitly requested by the user, follow
  `docs/prompt.md`, `docs/tracking.md`, and the referenced roadmap section. Do
  not apply that workflow to ordinary feature or maintenance requests.

## Architecture boundaries

- `routes/web.php`: route declarations and top-level authentication roles.
- `src/Controller/`: HTTP orchestration only—read the request, enforce the
  workflow boundary, call domain collaborators, and build a response.
- `src/Model/`: persisted entities, database queries, and data lifecycle rules.
- `src/Service/`: reusable workflows and integrations such as imports, exports,
  mail, payments, uploads, and retention.
- `src/Presentation/`: view-specific projections and aggregation that would
  otherwise make controllers or templates complex.
- `src/Validation/`: reusable input validation and normalization.
- `views/`: presentation only. Templates must not query the database or perform
  domain lookups. Extract repeated markup into focused partials/components.
- `lang/it.php` and `lang/en.php`: user-facing copy. Add or update both locales
  when introducing a translation key.
- `public/assets/css/app.css`: shared styles. Prefer classes over structural
  inline styles; reserve inline CSS variables for genuinely dynamic values.

Prefer a thin controller plus an explicit model, service, or presentation model
over large controller methods or templates containing data transformation.
Avoid N+1 queries; aggregate in SQL or in one pass over an already loaded result
set. For query-sensitive work, add query-count or equivalent regression evidence.

## Security and data safety

- Enforce authorization on the server and, where practical, in database query
  scope. Never trust hidden fields, query parameters, rendered IDs, or
  client-side validation as authorization.
- State-changing actions must use POST, validate CSRF, validate input, and use
  the narrowest applicable route role.
- Use prepared statements for values. Do not interpolate untrusted data into SQL.
- Escape untrusted HTML with `e()`. Only render trusted, intentionally generated
  markup without escaping.
- Do not expose stack traces or sensitive exception details to users. Log through
  the existing failure-reporting path.
- Never print, store, or commit secrets, `.env` contents, passwords, tokens,
  session identifiers, production records, or real athlete data. Tests and
  examples must use synthetic identities.
- Preserve privacy retention, entry snapshot, upload cleanup, and club/event
  ownership rules when changing athlete or registration workflows.

## Database and migrations

- Never edit an already shipped migration to repair a deployed schema. Add a
  forward migration with the next timestamped filename.
- Keep migrations safe for both a clean database and supported upgrade paths.
- Make repair migrations idempotent where schema drift is possible.
- Application features must not silently depend on an unapplied schema change.
- For migration changes, run both clean-schema and upgrade-path checks with
  `composer test:migrations`. This requires the isolated MySQL test setup
  described in `README.md`.

## Code and UI conventions

- Use `declare(strict_types=1);` in PHP source and test files.
- Follow PSR-12 and the repository PHPStan level. Add precise iterable PHPDoc
  where native PHP types cannot express the shape.
- Prefer enums and existing helpers for gender, belts, categories, money,
  localization, URLs, sessions, and CSRF rather than duplicating formatting.
- Keep methods and partials focused; use names that describe domain intent.
- Preserve routes, response semantics, and public behavior unless the request
  explicitly changes them.
- Maintain responsive table behavior, accessible labels, keyboard focus, and
  dark-theme compatibility when changing markup or CSS.
- Do not edit dependencies under `vendor/`, runtime files under `var/`, uploaded
  files, or generated coverage/build artifacts.

## Verification

Run the narrowest relevant test while iterating, then choose the final gate in
proportion to the change:

```sh
composer test -- --filter RelevantTest
composer check
```

Additional required checks:

- Query or list changes: demonstrate bounded query behavior.
- Migration changes: `composer test:migrations`.
- Deployment/build changes: `composer deploy:preflight`.
- Before a requested push: `composer ci` (the pre-push hook also runs it).

`composer check` covers metadata, syntax, PHPCS, PHPStan, dependency audit, and
the full PHPUnit suite. If an external dependency or audit check cannot run,
report exactly which check was not verified; do not describe the whole gate as
passing. Review `git diff --check` and the final diff before handing work back.

Every bug fix, security change, or data-integrity change requires a regression
test. New executable source should satisfy the repository's changed-code
coverage threshold.

## Git and handoff

- Do not commit, amend, push, open a pull request, or deploy unless the user
  explicitly asks for that action.
- Use Conventional Commits for all requested commits, for example:
  `fix(events): preserve closed-entry snapshots` or
  `refactor(events): simplify entries presentation`.
- Keep commits cohesive and avoid unrelated formatting or cleanup.
- Do not rewrite a pushed commit without explicit approval.
- In the final report, state the outcome, important files changed, checks run,
  any remaining risk, and whether changes are committed or pushed.
