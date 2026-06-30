# Competizioni Judo

Small, framework-free PHP MVC application for publishing judo events, managing
club athlete archives, registering athletes, and administering closed-event
records. This repository supplies application controls; production readiness
also depends on correct hosting, privacy, mail, backup, and operational setup.

## Supported features

| Capability | Routes | Access |
| --- | --- | --- |
| Home, public events, event details and entries | `/`, `/events.php`, `/event_details.php`, `/event_entries.php` | Public |
| Privacy notice and language switch | `/privacy`, `/language/switch` | Public |
| Deployment health and build revision | `/health` | Public, minimal JSON |
| Club registration and login | `/club_register.php`, `/club_login.php` | Public |
| Password recovery by email | `/club_forgot_password.php`, `/club_reset_password.php` | Public |
| Club athlete archive and event registration | `/club_area.php`, `/event_register.php` | Authenticated club |
| Athlete deletion | `/club_delete_athlete.php` | Authenticated club, POST + CSRF |
| Event and club administration | `/admin_manage_events.php`, `/admin_add_event.php`, `/admin_manage_clubs.php`, `/admin_edit_club.php` | Administrator |
| Event and club deletion | `/admin_delete_event.php`, `/admin_delete_club.php` | Administrator, POST + CSRF |

## Requirements

- PHP 8.2 or later with PDO MySQL, mbstring, fileinfo, and XML extensions
- MySQL 8.0 or 8.4
- a configured `PasswordResetMailer`; production uses the Aruba PHP-mail adapter
- Composer 2
- `rsync`, Bash, and `curl` for deployment artifact checks

## Local setup

1. Create an empty MySQL database and a dedicated local database user.
2. Run `composer install`.
3. Copy `.env.example` to `.env`, set `APP_ENV=local`, and fill every database,
   administrator, mail, and `PRIVACY_*` value with synthetic local data. Generate
   `ADMIN_PASS_HASH` with `password_hash()`; do not store a plaintext password.
4. Run `composer migrate`.
5. Run `composer serve` and open `http://localhost:8080`.

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
Closing an event atomically stores its competition snapshot. Schedule
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

Run the full gate before committing:

```sh
composer check
```

It validates Composer metadata, PHP syntax, coding style, PHPStan, PHPUnit, and
the Composer security audit. `composer test:migrations` needs an isolated MySQL
test account. `composer ci` also builds and verifies the exact production-only
artifact. The build includes only runtime directories and access-control marker
files, never `.env`, tests, development dependencies, logs, or uploaded files.

Project remediation evidence and sequencing live in
[audit.md](docs/audit.md), [roadmap.md](docs/roadmap.md), and
[tracking.md](docs/tracking.md). Continue work with [prompt.md](docs/prompt.md).
