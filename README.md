# Competizioni Judo

A small framework-free MVC PHP project scaffold with a professional layout: public front controller, PSR-4 autoloading, routing, controllers, views, configuration, environment loading, tests, static analysis, coding standards, Apache rewrites, and operational folders.

## Requirements

- PHP 8.2+
- PHP XML extensions: `dom`, `simplexml`, `xml`, `xmlwriter`
- Composer

## Quick Start

```bash
cp .env.example .env
composer install
composer serve
```

Open `http://localhost:8080`.

## Project Structure

```text
config/            Application configuration
public/            Web root and front controller
routes/            Route definitions
src/Controller/    HTTP controllers
src/Core/          Minimal framework primitives
src/Model/         Domain/data models
views/             PHP templates and layouts
tests/             PHPUnit tests
var/               Runtime cache and logs
```

## Quality Commands

```bash
composer check      # metadata, syntax, style, static analysis, tests, audit
composer format     # auto-fix PHP coding-standard issues
composer ci         # full check plus deploy artifact smoke build
```

## Git Hooks

This repository uses local Git hooks from `scripts/git-hooks/`.

- `pre-commit` runs the fast checks: Composer metadata validation and syntax checks for staged PHP files.
- `pre-push` runs `composer ci`, which executes the full local CI suite.

Enable them with:

```bash
git config core.hooksPath scripts/git-hooks
```

## Notes

- Prefer pointing your web server document root at `public/`.
- On shared hosting where the document root cannot be changed, upload the whole project and keep the root `.htaccess`; it rewrites traffic into `public/` and blocks direct access to application folders.
- Run `composer install --no-dev --optimize-autoloader` locally or on the hosting shell, then deploy `vendor/` together with the app if Composer is not available on the host.
- GitHub Actions deployment details are in `docs/deployment.md`.
- Keep secrets in `.env`; commit only `.env.example`.
- Replace the in-memory sample model with a database-backed repository when persistence is needed.
