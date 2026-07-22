# Competizioni Judo

Small, framework-free PHP MVC application for publishing judo events, managing
club athlete archives, registering athletes, and administering closed-event
records. This repository supplies application controls; production readiness
also depends on correct hosting, privacy, mail, backup, and operational setup.

## Supported features

| Capability | Routes | Access |
| --- | --- | --- |
| Public events and event details | `/events`, `/events/details` | Public |
| Event entries | `/events/entries` | Authenticated club or administrator |
| Privacy notice and language switch | `/privacy`, `/language/switch` | Public |
| Deployment health and build revision | `/health` | Public, minimal JSON |
| Club registration and login | `/clubs/register`, `/clubs/login` | Public |
| Password recovery by email | `/clubs/forgot-password`, `/clubs/reset-password` | Public |
| Club athlete archive and event registration | `/clubs/area`, `/events/register` | Authenticated club |
| Athlete CSV import and export | `/clubs/athletes-import`, `/clubs/athletes-export` | Authenticated club; import is POST + CSRF |
| Athlete deletion | `/clubs/delete-athlete` | Authenticated club, POST + CSRF |
| Event and club administration | `/admin/events`, `/admin/events/add`, `/admin/clubs`, `/admin/clubs/edit` | Administrator |
| Event and club deletion | `/admin/events/delete`, `/admin/clubs/delete` | Administrator, POST + CSRF |

## Requirements

- PHP 8.4 or later with PDO MySQL, mbstring, fileinfo, and XML extensions
- MySQL 8.0 or 8.4
- a configured `PasswordResetMailer`; production uses the Aruba PHP-mail adapter
- Composer 2
- `rsync`, Bash, and `curl` for deployment artifact checks

## Local setup

1. Create an empty MySQL database and a dedicated local database user.
2. Run `composer install`.
3. Run `composer hooks:install` once for the clone so Git uses the repository
   hooks from `scripts/git-hooks/`.
4. Copy `.env.example` to `.env`, set `APP_ENV=local`, and fill every database,
   administrator, and mail value with synthetic local data. Review the
   `APP_OWNER*`, `APP_WEBHOST*`, and retention facts. Generate `ADMIN_PASS_HASH`
   with `password_hash()`; do not store a plaintext password.
5. Run `composer migrate`.
6. Run `composer serve` and open `http://localhost:8080`.

Local/development startup applies forward migrations automatically. Production
operators must run `composer migrate` explicitly before directing traffic to a
new release. The consolidated baseline supports empty databases and databases
that recorded the complete pre-squash migration chain; an incomplete historical
chain fails closed and requires operator review.

## Architecture

`public/index.php` is the front controller. `routes/web.php` maps requests to
small controllers under `src/Controller`; models and explicit services own
database and lifecycle work; `views/` and `lang/` own presentation. The design
intentionally avoids a general application framework and dependency container.

Runtime state is not a code artifact:

- MySQL owns clubs, athletes, events, registrations, and security records.
- `public/uploads/events/` owns event documents. Replacement and event deletion
  purge old documents; Git and deployment artifacts exclude their contents.
- `var/log/` owns application logs and must be rotated by the host.
- backups are host-owned and must follow the configured retention policy.

## Privacy and security

The public `/privacy` notice derives controller identity, legal bases,
processors, transfer facts, and operational retention periods from environment
variables. Production startup fails if required values are missing or malformed.
The comments in `.env.example` identify the required GDPR transparency data.
Those values must describe the real deployment; software cannot choose a lawful
basis or validate the controller's arrangements.

Live athlete categories are calculated from source data for the event year.
Closing an event atomically stores its event snapshot. Schedule
`composer privacy:purge` daily to remove closed-event entry snapshots after at
most one year. Event uploads are deleted when replaced or when their event is deleted.
Administrators are warned to export live athlete records before deleting a club.

The application uses only its technical session cookie and does not load
analytics or profiling cookies. Authentication is server-side, destructive
actions require POST and CSRF validation, authorization is scoped at the server
and database boundaries, uploaded event documents are allow-listed, and errors
are logged without exposing stack traces in production. These controls do not
replace HTTPS, least-privilege database credentials, processor agreements,
rights/breach procedures, backup expiry, monitoring, or independent review.

See [deployment operations](docs/deployment.md) for the production checklist.

## Verification and deployment

Run the same non-secret quality and deployment preflight used by GitHub Actions
before pushing:

```sh
composer ci
```

`composer check` remains the faster quality-only check.

Install the repository hooks once per clone if you have not already:

```sh
composer hooks:install
```

It validates the lock-file installation on the current PHP platform, Composer
metadata, workflow definitions, PHP syntax, coding style, PHPStan, PHPUnit, and
the Composer security audit. `composer test:migrations` needs an isolated MySQL
test account. Copy `dev.env.example` to `dev.env`, fill the local MySQL
administrator and test-account values, then run `composer test:migrations:prepare`
once to create the dedicated test user and grants. `composer test:migrations`
and `composer ci` read `dev.env` when present. `composer ci` also enforces the
changed-source coverage policy, builds and boots the exact production-only
artifact, and stages/verifies the root router uploaded by the deploy workflow.
The build includes only runtime directories and access-control marker files,
never `.env`, tests, development dependencies, logs, or uploaded files.

The pre-commit hook validates staged Composer metadata and PHP syntax only when
those files are staged. The pre-push hook runs `composer ci`; it verifies that
the locked dependencies can be installed without changing the local vendor
directory. Run `composer install` after changing `composer.lock` or when the
hook reports that dependencies are missing. FTPS upload, signed server-side
migrations, and remote health verification run only in GitHub Actions because
they require environment credentials and a live deployment target. Workflow
validation uses Go to install the pinned `actionlint` version once, then reuses
that binary.

Project remediation evidence and sequencing live in
[audit.md](docs/audit.md), [roadmap.md](docs/roadmap.md), and
[tracking.md](docs/tracking.md). The current post-remediation findings and
incremental PR plan are in
[review-tracking-2026-07-13.md](docs/review-tracking-2026-07-13.md).
Continue work with [prompt.md](docs/prompt.md).

## License

This project is proprietary software. All rights are reserved by ASD Scuola
Judo Shòbò-shi; see [LICENSE](LICENSE) for the applicable terms.
