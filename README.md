# Competizioni Judo

A production-ready, framework-free MVC PHP application for managing judo competitions. Features event management, club registration, athlete entry management with automatic judo category calculation, admin dashboard, and bilingual (Italian/English) support.

## Features

- **Event Management** – Create, edit, publish, and close competitions with poster/info file uploads
- **Club Registration & Login** – Self-registration with forgot/reset password flow
- **Athlete Management** – CRUD for athletes within each club's area
- **Event Registration** – Clubs register athletes for events with duplicate detection
- **Judo Category Calculation** – Automatic age class and weight category determination
- **Bilingual UI** – Italian (default) and English with locale switching
- **Admin Dashboard** – Manage clubs, events, and view entries with pagination

## Requirements

- PHP 8.2+
- MySQL 8.0+ or compatible
- PHP XML extensions: `dom`, `simplexml`, `xml`, `xmlwriter`
- Composer

## Quick Start

```bash
cp .env.example .env
# Edit .env with your database credentials
composer install
composer serve
```

Open `http://localhost:8080`.

## Project Structure

```text
config/            Application configuration (app.php)
public/            Web root and front controller
routes/            Route definitions (web.php)
src/Controller/    HTTP controllers
src/Core/          Minimal framework primitives (Router, View, Request, Session, Cache)
src/Model/         Domain/data models (Club, Event, Athlete, Entry, others)
views/             PHP templates and layouts
tests/             PHPUnit unit tests
migrations/        SQL migration files
lang/              Translation files (en.php, it.php)
var/               Runtime logs
scripts/           Build, migration, and git hook scripts
docs/              Audit, deployment, and tracking documentation
.github/workflows/ CI and deployment workflows
```

## Security

All Phase 1-2 security hardening is implemented and verified:

- ✅ CSRF protection on all POST forms
- ✅ Session cookie hardening (HttpOnly, Secure, SameSite=Lax)
- ✅ Login rate-limiting (5 attempts / 5 min cooldown)
- ✅ Session regeneration after login
- ✅ Password reset token hardening (single-use, previous tokens invalidated)
- ✅ Open redirect prevention
- ✅ Security headers (HSTS, X-Content-Type-Options, X-Frame-Options, etc.)
- ✅ File upload MIME validation (server-side `finfo` check)
- ✅ Auto-migrations disabled in production
- ✅ Prepared statements throughout (no SQL injection)

## Quality Commands

```bash
composer check      # metadata, syntax, style, static analysis, tests, audit
composer format     # auto-fix PHP coding-standard issues (PSR-12)
composer ci         # full check plus deploy artifact smoke build
composer analyse    # PHPStan static analysis
composer test       # PHPUnit tests
```

## Git Hooks

This repository uses local Git hooks from `scripts/git-hooks/`.

- `pre-commit` runs fast checks: Composer metadata validation and syntax checks for staged PHP files.
- `pre-push` runs `composer ci`, which executes the full local CI suite.

Enable them with:

```bash
git config core.hooksPath scripts/git-hooks
```

## Deployment

The project deploys from GitHub Actions over FTP/FTPS to shared hosting (Aruba, Altervista, etc.).

| Branch | Environment | URL |
|--------|-------------|-----|
| `main` | Production | `https://www.competizionijudo.it` |
| `dev`  | Development | `https://dev.competizionijudo.it` |

See `docs/deployment.md` for detailed setup, GitHub secrets, and first-deployment instructions.

## Notes

- Point your web server document root at `public/`.
- On shared hosting where the document root cannot be changed, the root `.htaccess` rewrites traffic into `public/` and blocks direct access to application folders.
- Keep secrets in `.env`; commit only `.env.example`.
- For custom domains, configure the hosting control panel so each subdomain points to its respective subdirectory (`prod/` or `dev/`).
- See `docs/audit.md` for the full security and architecture audit.
- See `docs/tracking.md` for implementation progress.
