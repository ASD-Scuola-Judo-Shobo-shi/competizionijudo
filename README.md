# Competizioni Judo

Small, framework-free PHP MVC application for publishing judo events, managing
club athlete archives, registering athletes, and administering closed-event
records. This repository supplies application controls; production readiness
also depends on correct hosting, privacy, mail, backup, and operational setup.

Clubs can create, edit, publish, close, and archive their own tournaments through
`/clubs/events` and `/clubs/events/details` (POST archiving: `/clubs/events/archive`).
Published tournaments use the standard registration and payment workflows.
A tournament can be managed by multiple clubs. Its creator club and administrators
can select additional managing clubs in the event form. Shared managers can edit
and archive the tournament; they cannot change access or permanently delete it.
Administrators retain control over all tournaments; deleting an organizer club
preserves its tournaments for administration. Archived tournament settings are
read-only; the tournaments are hidden from public event lists and closed to registration. Entries and documents
are preserved under the existing retention rules. Administrators can archive via
`/admin/events/archive` and retain exclusive access to tournament deletion.
The Active/Archived filters expose both lists. Apply migrations before enabling
this feature in production (`composer migrate`).

Administrators can preview formatting across clubs and athlete profiles at
`/admin/normalization`, then apply selected records. The tool examines 50 records
per page, detects stale previews, and applies each selection atomically. It
preserves club ownership, sporting fields, notes, and frozen competition snapshots;
it does not merge athletes between clubs. Conflicting unique identifiers roll
back the entire selection and must be resolved through the existing edit forms.

## Supported features

| Capability | Routes | Access |
| --- | --- | --- |
| Public events and event details | `/events`, `/events/details` | Public |
| Event entries | `/events/entries` | Public club totals; authenticated aggregates; own-club details; minimized closed rosters for participating clubs with current agreements; complete administrator view |
| Printable event roster | `/events/entries/print` | Authenticated club; complete own-club athlete list as a color, grayscale-friendly PDF for open or closed events |
| Privacy notice, Terms of Service, and language switch | `/privacy`, `/terms`, `/language/switch` | Public |
| Deployment health and build revision | `/health` | Public, minimal JSON |
| Club registration and login | `/clubs/register`, `/clubs/login` | Public |
| Club Terms and privacy-warranty acceptance | `/clubs/agreements` | Authenticated club; current versions required before athlete-data mutations or cross-club closed-roster access |
| Password recovery by email | `/clubs/forgot-password`, `/clubs/reset-password` | Public |
| Club athlete archive and event registration | `/clubs/area`, `/events/register` | Authenticated club |
| Athlete spreadsheet import and CSV export | `/clubs/athletes-import`, `/clubs/athletes-export` | Authenticated club; import is POST + CSRF |
| Athlete deletion | `/clubs/delete-athlete` | Authenticated club, POST + CSRF |
| Event and club administration | `/admin/events`, `/admin/events/details`, `/admin/clubs`, `/admin/clubs/edit` | Administrator |
| Club athlete viewing and CSV export | `/admin/clubs/athletes`, `/admin/clubs/athletes/export` | Administrator |
| Temporary historical duplicate repair | `/admin/maintenance/athlete-duplicates` | Administrator, feature flag + CSRF + explicit confirmation |
| Event entry CSV export | `/admin/events/export` | Administrator |
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

The implemented controls, current limitations, verification evidence, and
post-deployment checks are summarized in the
[current security baseline](docs/security.md).

The public `/privacy` notice derives controller identity, legal bases,
processors, transfer facts, and operational retention periods from environment
variables. Production startup fails if required values are missing or malformed.
The comments in `.env.example` identify the required GDPR transparency data.
The public `/terms` page is the versioned club contract. Registration records
the accepted Terms version and language separately from the Article 14
notice-delivery warranty. Material Terms changes require a version bump and
fresh club acceptance before further athlete-data mutations or cross-club
closed-roster access. After registrations close, a participating club with no
registration exception may see the minimized roster (name, club, age class,
weight category, gender, and belt) only after both current agreements are on
record. Public users remain limited to aggregates, and cross-club CSV export is
not available.
Those values must describe the real deployment; software cannot choose a lawful
basis or validate the controller's arrangements.

Live athlete categories are calculated from source data for the event year.
Closing an event atomically stores its event snapshot. Schedule
`composer privacy:purge` daily, or configure Aruba's PHP cron to run
`scripts/purge-expired-data.php`, to remove closed-event entries once they reach
one year and expired account-workflow records. Event uploads are deleted when
replaced or when their event is deleted.
Administrators are warned to export live athlete records before deleting a club.

Clubs can request a tournament data update from each athlete in a closed roster
(`/events/entry-data`). Administrators review the exact before/after values via
`/admin/events/pending-updates`, or edit a tournament entry directly via
`/admin/events/entry-data`. Approval updates only that tournament's snapshot;
its screens and CSV exports use the approved values. Rejection preserves the
existing record. Requests and decisions retain reasons and the administrator
role; the application currently uses a shared administrator identity.

The competition admin page links to `/admin/events/differences`, which compares
competition page values, current CSV export data, and current athlete profiles.
Closing registrations freezes entry data even before the competition date.
Before the event cutoff, administrators can align all differing frozen entries
with current profiles after entering a reason. Alignment is atomic, rejects
outdated previews, records an approved review for each changed entry, and
preserves existing snapshot retention dates. Other competitions and athlete
profiles are unaffected.

Athlete inserts and relevant edits record revisions through MySQL triggers,
including spreadsheet imports and duplicate reconciliation. Apply the forward
migration with a database account that can create triggers before serving the
new release. Revision tracking starts at deployment; existing snapshots are
preserved and earlier athlete revisions cannot be reconstructed. The cutoff is
the end of the tournament date in `Europe/Rome`, with revision timestamps stored
in UTC. An administrator may approve an eligible revision later, but cannot
apply a newly entered correction after that cutoff. Ended tournaments that were
left open use only eligible recorded revisions or an existing approved snapshot.

`/admin/events/outliers` reviews all enrolled athletes using tournament-year
age and the configurable thresholds in `config/entry_review.php`. These broad
review prompts never change or reject an entry. Review history is removed with
its tournament entry. Daily privacy purge removes unreferenced revisions older
than one year, retaining the latest revision corresponding to the live profile;
approval never refreshes an existing snapshot's retention date.

### Repairing historical athlete duplicates

Imports made before the case-insensitive identity reconciliation was introduced
may have left duplicate athletes. On Aruba production, set the GitHub production
environment variable `ATHLETE_DUPLICATE_MAINTENANCE=true`, deploy, sign in as the
administrator, and open `/prod/admin/maintenance/athlete-duplicates`. Preview
one club at a time before applying. The page requires the same selection to have
been previewed during the previous 30 minutes, a verified-backup confirmation,
CSRF validation, and the exact displayed confirmation phrase. Set the variable
back to `false` and redeploy immediately after the repair.

For local or other command-line environments, use:

```sh
composer athletes:deduplicate -- --club-id=201
composer athletes:deduplicate -- --club-id=201 --apply
```

Run it for all clubs by omitting `--club-id`. Before either production method,
stop athlete imports and event registrations and take a verified database
backup. The cleanup keeps the lowest athlete ID, reconciles fields using the
same rules as import, reassigns safe
event entries, and reports every decision. Groups where both athlete IDs are
registered for the same event are left unchanged for manual review; an apply
CLI run exits with status 2 while any such groups remain. Same-name athletes
with different birth dates are reported but never merged automatically.
Re-running either method is idempotent.

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
hook reports that dependencies are missing. FTPS upload, Basic-Auth-protected
server-side migrations, and remote health verification run only in GitHub Actions because
they require environment credentials and a live deployment target. Workflow
validation uses Go to install the pinned `actionlint` version once, then reuses
that binary.

The June [audit](docs/audit.md), [roadmap](docs/roadmap.md), and
[tracker](docs/tracking.md), plus the July
[post-remediation review](docs/review-tracking-2026-07-13.md), are retained as
historical evidence. They describe their audited revisions and must not be used
as the current vulnerability status. The associated
[continuation prompt](docs/prompt.md) applies only when explicitly resuming that
historical roadmap workflow.

## License

This project is proprietary software. All rights are reserved by ASD Scuola
Judo Shòbò-shi; see [LICENSE](LICENSE) for the applicable terms.
