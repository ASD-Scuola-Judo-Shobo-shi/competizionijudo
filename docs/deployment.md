# Deployment

GitHub Actions builds an application artifact without runtime secrets and
synchronizes it to the branch-specific FTP directory. The artifact never
contains `.env`. Instead, each deploy job renders an environment-specific
`.env` from GitHub Actions environment variables/secrets and uploads it
separately to the target directory.

Each artifact contains a SHA-256 manifest of every deployable file. FTPS uses
the prior remote manifest to upload only added or changed application files
and remove code files retired by the new artifact; it uploads the new manifest
last. A missing, malformed, or older-protocol remote manifest triggers one
complete transfer instead. Temporary filenames and retries protect each copy,
then the workflow downloads every expected file again and compares its
SHA-256 hash on the runner. It verifies the generated `.env` and root router
files the same way. A byte mismatch, missing file, or failed rename stops the
deployment before migrations run. A mismatch records its paths, retransfers
only those files plus the manifest, and runs another read-back check up to
`FTP_VERIFY_RETRIES` times (default and minimum: 4); configure a larger value
with the GitHub Actions environment variable only when the FTP service is
demonstrably transiently unreliable.

## First-time environment provisioning

Before directing traffic to a new `prod/` or `dev/` directory, an authorized
repository/hosting operator must:

1. Create or update the GitHub Actions `production` environment. Create the
   `development` environment only when deploying the `dev` branch.
2. Keep `FTP_PASSWORD` as a repository Actions secret, and `ADMIN_USER`,
   `FTP_BASE_DIR`, and `APP_URL` as repository Actions variables. Set
   `APP_URL` to the canonical HTTPS site root with a trailing slash (for
   example, `https://www.competizionijudo.it/`). Store `DB_PASS`,
   `ADMIN_PASS_HASH`, and a separately generated `MIGRATIONS_TOKEN` as
   environment secrets. Store non-secret environment
   values such as `DB_HOST`, `DB_NAME`, `DB_USER`, `APP_OWNER*`,
   `APP_WEBHOST*`, retention days, `FTP_SERVER`, and `FTP_USERNAME` as
   environment variables. Each environment must define its directory name as
   `FTP_DEPLOY_DIR` (for example, `prod` or `dev`), without a leading slash,
   parent-directory segment, or `.`. The workflow combines it with the common
   `FTP_BASE_DIR` for the app upload; `FTP_BASE_DIR` is also used for the shared
   root router upload. For this Aruba Linux hosting account, the upload wrapper
   translates `ftp.competizionijudo.it` to the certificate-covered canonical
   endpoint `ftplnx02.aruba.it`; TLS certificate verification must remain
   enabled.
3. The workflow appends the selected environment path to the repository
   `APP_URL`: `prod` for `main` and `dev` for `dev`. It sets
   `APP_ENV=production` on `main`, `APP_ENV=development` on `dev`, and
   `APP_DEBUG=false` in both environments. Production and development must use
   separate database and administrator credentials.
4. Review every blank required key from `.env.example` and verify the
   `APP_OWNER*`, `APP_WEBHOST*`, and retention facts displayed by the privacy
   notice. Use a dedicated least-privilege database account and a password hash
   produced with PHP's `password_hash()` for `ADMIN_PASS_HASH`; never store the
   administrator's plaintext password.
5. **Run the deploy workflow.** The deploy job stages and uploads two root
   router files to the FTP host root alongside the environment-specific
   application artifact:
   - `root.htaccess` → `.htaccess` (renamed at stage time)
   - `index.php` → `index.php` (as-is)
   
   `root.htaccess` serves as the Apache security boundary and front controller.
   It rejects unknown hosts, redirects HTTP requests through fixed canonical
   destinations, blocks direct access to environment files, source, migrations,
   manifests, and other internal resources below `prod/`, `dev/`, or `legacy/`,
   and rewrites other non-file, non-directory requests to `index.php`. The PHP
   front controller handles the remaining canonical redirects and internally
   routes each request to the correct environment directory.
   
   These files live outside any per-environment directory (they go to the FTP
   root, the directory that contains `prod/`, `dev/`, etc.). Every deploy
   re-uploads them, so changes to `root.htaccess` or `index.php` take effect
   on the next deployment.
6. Verify that `https://www.competizionijudo.it/health` returns HTTP 200 before
   enabling traffic. The workflow renders `.env` inside GitHub Actions and
   uploads it to the target directory after the application files. If an
   operator makes an emergency manual `.env` edit on the host, they must
   immediately mirror that change back into the matching GitHub environment or
   the next deploy will overwrite it.
7. The deploy workflow uploads the artifact and generated `.env`, then sends an
   HTTPS `POST` request to `APP_URL/prod/migrations/` for production or
   `APP_URL/dev/migrations/` for development (or the optional environment
   `MIGRATIONS_URL` override). The
   endpoint runs on the hosting server, where MySQL is locally reachable. It
   requires the `MIGRATIONS_TOKEN` environment secret in the
   `X-Migration-Token` request header. An unauthenticated request receives HTTP
   401. A migration failure fails the workflow and returns a
   password-redacted diagnostic to the authenticated caller.

The workflow never connects directly to MySQL, so GitHub runner IP addresses
do not need database access. Do not create `MIGRATION_DB_*` GitHub entries for
this flow. Deployment concurrency queues a newer run instead of cancelling an
active one, so an in-progress migration request is not interrupted by a new
push.

## Post-deployment security verification

Run these checks after changing the shared root router or an environment
artifact. Use only synthetic paths and values; never place real tokens or
credentials in terminal history or CI logs.

1. Confirm `/prod/.env`, `/prod/.maintenance`, a known migration SQL path, and
   the deployment manifest return HTTP 403. Repeat with `/dev` when that
   environment is deployed.
2. Send a request to the origin with a synthetic invalid `Host` header and
   confirm HTTP 400 with no redirect to the supplied host. When testing by IP,
   preserve the canonical hostname for TLS SNI and override only the HTTP Host
   header.
3. Request `/prod/clubs/login` and confirm the response includes an enforced
   `Content-Security-Policy`, `X-Frame-Options: DENY`,
   `X-Content-Type-Options: nosniff`, `Strict-Transport-Security`, and
   `Cache-Control: private, no-store, max-age=0`.
4. Request the reset-password page with a synthetic invalid token and confirm
   `Referrer-Policy: no-referrer` and the same no-store cache policy.
5. Confirm `/prod/health` still returns the deployed revision expected by the
   workflow.

`composer deploy:preflight` verifies the exact artifact and root-router files,
but it does not execute Apache on the hosting provider. Record the live results
with the deployment evidence. See the [current security baseline](security.md)
for control scope and remaining risks.

`ATHLETE_DUPLICATE_MAINTENANCE` is an optional, temporary boolean environment
variable. It defaults to `false`; set it through the applicable GitHub Actions
environment only for the supervised repair procedure below.

The runner records each successfully completed migration in
`schema_migrations`, so repeating a completed deployment is a no-op. Forward
migrations must also guard their individual schema changes, allowing a retry to
finish after MySQL has implicitly committed DDL before the version could be
recorded. The runner refuses an existing application schema with no
`schema_migrations` table: it makes no changes and does not create a ledger.
Back up and repair or rebuild that legacy database through an operator-directed
procedure instead of treating it as a fresh installation.

### Manual event-schema repair

If an older production database is missing `events.max_participants` or the
`event_registration_exceptions` table, an administrator can temporarily upload
`scripts/repair-event-schema.php` under a newly generated, unguessable name in
the hosting root, beside the root `index.php` that contains the `prod/`
directory. Do not upload it inside `prod/public/`: the root router sends those
URLs to the application and they return 404. Take and verify a database backup
first. Open the root-level temporary URL in a browser (without `/prod/`) and
authenticate through the HTTPS Basic Auth prompt using the production
`ADMIN_USER` value for both the username and password. Type `REPAIR EVENT SCHEMA` exactly to
execute it. The script accepts only that authenticated POST request and changes
only those two schema contracts. Delete the temporary public PHP file
immediately after a successful run.

The script refuses to proceed when the migration history says the schema is
present but its required column or table is absent. Do not manually insert a
`schema_migrations` record: the script records a version only after verifying
the matching schema. Some legacy production databases have no
`schema_migrations` table at all. In that case, the script applies and verifies
only the two event-schema changes, and deliberately does not create or seed a
migration ledger. Establishing a complete migration history is a separate,
backed-up maintenance task.

The consolidated schema baseline can initialize an empty database or adopt a
database that has recorded every pre-squash migration. It deliberately rejects
existing application tables without a migration ledger, as well as partial
pre-squash histories. Back up the database and investigate its migration records
instead of bypassing this guard.

### Historical athlete duplicate cleanup on Aruba

Aruba Linux Basic has no SSH, so historical athlete reconciliation runs through
a temporary administrator-only application page rather than through the CLI or
a full database replacement. The page is disabled by default and fails closed.

1. Export and verify a current MySQL backup. Stop athlete imports and event
   registrations for the maintenance window.
2. Set the GitHub Actions `production` environment variable
   `ATHLETE_DUPLICATE_MAINTENANCE=true` and deploy `main`.
3. Sign in as the application administrator and open the canonical production
   path `/prod/admin/maintenance/athlete-duplicates` (or append
   `/admin/maintenance/athlete-duplicates` to the deployed production
   `APP_URL`).
4. Select one club and run the read-only preview. Review safe merges, field
   reconciliation, blocked same-event registrations, and same-name athletes
   with different birth dates.
5. For the same selection, confirm the verified backup, type
   `APPLY ATHLETE CLEANUP`, and apply within 30 minutes of the preview. The
   operation re-evaluates and locks current rows in its database transaction;
   safe groups are applied while blocked groups remain unchanged.
6. Run another preview for the club. It should show no safe duplicate groups.
   Save the report only in the controller's restricted maintenance records,
   without placing athlete details in tickets or public CI logs.
7. Set `ATHLETE_DUPLICATE_MAINTENANCE=false` (or remove the variable) and deploy
   again. Confirm the submenu item disappears and the authenticated route
   returns 404.
8. Verify athlete lists and affected event entries, then take a post-repair
   backup.

The page requires an administrator session, the route-level administrator
policy, CSRF validation, the recent matching preview, the backup confirmation,
and the exact phrase. Its responses are private and non-cacheable. Do not leave
the feature flag enabled after the maintenance window. Aruba's MySQL panel can
export and import SQL files, but a dump-edit-replace cycle is only a fallback
because any production writes after the dump would be lost.

Rotating credentials is now a GitHub environment operation: update the affected
environment variable or secret, redeploy the branch, and verify `/health`.
Do not commit generated `.env` files, workflow artifacts, ticket attachments,
or chat messages containing runtime secrets. Operations must assign the named
owner and secure provisioning channel for each hosting environment before its
first deployment.

## Required application settings

`.env.example` is the authoritative non-secret inventory. The deploy workflow
expects GitHub environment entries with the same names for all blank required
keys and reuses the template defaults for `APP_NAME`, `APP_LOCALE`,
`APP_TEST_RESET_LINKS`, `EVENTS_UPCOMING_LIMIT`,
`ATHLETE_DUPLICATE_MAINTENANCE=false`, and
`PASSWORD_RESET_MAILER=aruba` unless a future workflow override is added.
Startup requires `APP_URL`, all four `DB_*` settings, `ADMIN_USER`,
`ADMIN_PASS_HASH`, `MIGRATIONS_TOKEN`, `PASSWORD_RESET_MAILER`,
`MAIL_FROM_ADDRESS`, all four
`APP_OWNER*` settings, both `APP_WEBHOST*` settings, and positive
`APP_LOG_RETENTION_DAYS` and `APP_BACKUP_RETENTION_DAYS` values. It validates
the public owner contact email. The controller must verify the published facts
and application-owned legal text before deployment and update the notice if the
actual processing changes.

If required production configuration is missing, startup returns a server error
without exposing values. The operator should inspect `var/log/application.log`;
events such as `configuration.missing.db_name` identify the setting to provision,
while exception messages and configuration values remain redacted.

The `MIGRATION_TEST_*` variables documented in `dev.env.example` belong only
to the isolated local/CI migration smoke harness. Do not provision them in a
deployed application environment. The migration route uses only the deployed
`MIGRATIONS_TOKEN` and never returns a token, password, or configuration value
in an HTTP response or application log.

## Session environment boundary

The root router passes the selected path prefix to the application before its
bootstrap starts a PHP session. Production therefore uses the `/prod` cookie
path and development uses `/dev`; each environment also receives a distinct
cookie name and an environment marker inside the PHP session record. Keep the
workflow-provided `APP_ENV` values distinct (`production` for `main`,
`development` for `dev`) and do not manually reuse session cookies between
environments.

The first deployment containing this boundary intentionally invalidates legacy
`PHPSESSID` sessions and any session record without the matching environment
marker. Users must authenticate again once; no database migration or runtime
data cleanup is required. Verify both `/prod/health` and `/dev/health` after
deployment, then confirm that a login in one environment is anonymous in the
other.

## Aruba Linux Basic prerequisites

The application targets Aruba Linux Basic without SSH or a third-party mail
service. Aruba documents PHP `mail()` testing in the hosting control panel and
uses the domain postmaster identity for site-generated messages. The generic
`PasswordResetMailer` boundary contains that behavior in the `aruba` adapter;
changing provider does not change the controller or reset-token lifecycle.

Before enabling recovery, create/verify the postmaster mailbox and run Aruba's
PHP mail test from **Strumenti e impostazioni > Gestione PHP > Test PHP mail**.
Then request one reset for a synthetic club and submit one synthetic club
registration. Confirm delivery and sender for both messages, plus the reset's
one-hour expiry and one-time use. Transport failures are logged as
`club.password_reset_delivery_failed` or
`club.registration_confirmation_delivery_failed`.

Linux Basic does not include a database or database backup by default. Purchase
and provision the MySQL add-on before deployment; also activate a backup policy
that satisfies the published `APP_BACKUP_RETENTION_DAYS` value. Do not claim
backup retention in the privacy notice unless that service is actually active.

References: [Aruba PHP mail test](https://guide.hosting.aruba.it/hosting/hyper/hyper-linux/gestire-la-versione-php.aspx),
[Aruba site-mail sender behavior](https://guide.aruba.it/hosting-e-domini/hosting/gestione-strumenti-hosting/pubblicazione-gestione-sito/sostituire-form-mail-aruba),
and [Linux Basic plan comparison](https://hosting.aruba.it/web-hosting/linux).

## Runtime data and privacy retention

`public/uploads/events/`, `var/log/`, the database, and backups are runtime data
owned by the server operator. Code artifacts contain only the upload directory's
access-control file and must never overwrite or synchronize runtime upload
contents. The application deletes event documents when they are replaced and
when their event is deleted.

Schedule `composer privacy:purge` at least daily. It deletes closed-event entry
snapshots older than one year, enforcing a one-year maximum; monitor its exit status. Configure log rotation
to delete application logs after `APP_LOG_RETENTION_DAYS`, and configure the
backup system to delete backups after `APP_BACKUP_RETENTION_DAYS`. Those two
host-level policies are not implemented by the PHP process. Test both restores
and expiry, and document any processor that can access backups or logs.

Before going live, the controller must also establish procedures for data-subject
requests and breaches, confirm club authority for athletes and minors, sign the
required processor agreements, and verify Aruba's subprocessors and any
international-transfer safeguards. The public notice is at `/privacy`.

## Post-deployment health and rollback

Every artifact contains a `REVISION` file generated from the complete Git commit
SHA. `GET /health` performs a database `SELECT 1` and returns only `status` and
that revision as non-cacheable JSON. Both production and development jobs call
the endpoint after FTPS upload and fail unless HTTP 200 reports the exact SHA
that was built. Override `PRODUCTION_HEALTH_URL` or `DEVELOPMENT_HEALTH_URL`
only when the canonical host differs from the workflow defaults.

The deployment manifest is the per-environment state record. Normal app sync
removes code files that were present in the preceding manifest but are absent
from the new one. The application sync still excludes `.env`, while a dedicated
runtime-config sync uploads the generated `.env`. Runtime uploads/logs that
never enter the manifest and the independent `legacy/` directory are preserved.
Do not delete the remote deployment manifest manually: the next deployment
will safely fall back to a complete transfer, but will lose the fast path.

Repository administrators own rollback execution. Record the last healthy SHA
after each deployment. If health verification fails:

1. Do not reverse database migrations. Completed versions are recorded and
   skipped on retry; the guarded forward migrations can be retried after the
   failure cause is corrected.
2. In GitHub Actions, run the **Deploy** workflow from the affected branch and
   enter the last healthy complete SHA as `deployment_ref`.
3. Confirm the rollback run passes build gates and `/health` reports that SHA.
4. Inspect `var/log/application.log` and the failed workflow before attempting a
   corrected release.

Before the first release containing this health contract, download the current
application directory and root `.htaccess` through FTPS/File Manager. That
snapshot is the fallback for a pre-health revision, which cannot satisfy the new
SHA check. If the optional Aruba hosting/database backup services are active,
their control-panel restore is an additional fallback, not a substitute for the
known-good application snapshot. A code rollback does not roll back MySQL data.

The operational baseline should be reviewed against the official
[GDPR text](https://eur-lex.europa.eu/eli/reg/2016/679/2016-05-04/eng), the
[EDPB privacy-by-design guidance](https://www.edpb.europa.eu/topics/ai-and-technology/privacy-by-design-and-by-default_en),
and the [Italian authority's cookie guidance](https://www.garanteprivacy.it/web/guest/home/docweb/-/docweb-display/docweb/9677876).
These application notes are not a substitute for the controller's legal and
organizational assessment.
