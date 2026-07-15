# Deployment

GitHub Actions builds an application artifact without runtime secrets and
synchronizes it to the branch-specific FTP directory. The artifact never
contains `.env`. Instead, each deploy job renders an environment-specific
`.env` from GitHub Actions environment variables/secrets and uploads it
separately to the target directory.

## First-time environment provisioning

Before directing traffic to a new `prod/` or `dev/` directory, an authorized
repository/hosting operator must:

1. Create or update the GitHub Actions environments named `production` and
   `development`. Use the same key names as `.env.example`.
2. Store only sensitive values as environment secrets: at minimum `DB_PASS`,
   `ADMIN_PASS_HASH`, and `FTP_PASSWORD`. Store non-secret values such as
   `DB_HOST`, `DB_NAME`, `DB_USER`, `ADMIN_USER`, `APP_URL`, `APP_OWNER*`,
   `APP_WEBHOST*`, retention days, `FTP_SERVER`, and `FTP_USERNAME` as
    environment variables. Set `FTP_BASE_DIR` to the common base directory
    (e.g. `competizionijudo/`), and `FTP_PROD_DIR` / `FTP_DEV_DIR` to the
    environment-specific subdirectories (e.g. `prod/` and `dev/`). The
    workflow combines them as `FTP_BASE_DIR/FTP_PROD_DIR` and
    `FTP_BASE_DIR/FTP_DEV_DIR`. Do not use `/`, `./`, or absolute-looking
    values such as `/prod/`.
3. Set `APP_URL` to the canonical HTTPS base URL for the target environment.
   The workflow sets `APP_ENV=production` on `main`, `APP_ENV=development` on
   `dev`, and `APP_DEBUG=false` in both environments. Production and
   development must use separate database and administrator credentials.
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
   
   `root.htaccess` serves as the Apache front controller: it enforces HTTPS and
   rewrites all non-file, non-directory requests to `index.php`. The PHP front
   controller handles subdomain redirects and internally routes each request to
   the correct environment directory (`prod/`, `dev/`, or `legacy/`).
   
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
7. The deploy workflow runs `php scripts/run-migrations.php` directly from the
   built artifact before it uploads application code. It connects to MySQL with
   `MIGRATION_DB_*` GitHub environment values when present, otherwise the
   existing `DB_*` values. A failed database connection or migration fails the
   workflow before upload. It does not require SSH or any other access to the
   web host. Then perform the documented deployment smoke check before
   enabling traffic.

The MySQL service must accept a direct connection from the GitHub Actions
runner. The workflow never opens an SSH, FTP-shell, or HTTP migration session
to the web host. Deployment concurrency queues a newer run instead of
cancelling an active one, so an in-progress migration is not interrupted by a
new push.

Automatic deployments accept only additive `CREATE TABLE IF NOT EXISTS`
forward migrations after the consolidated baseline. They are safe to retry if
the table was created before a failed run could record the migration. Any
index, column, data, rename, or destructive change requires a database backup
and a separately approved maintenance procedure; it is intentionally blocked
from the automatic deployment path.

The consolidated schema baseline can initialize an empty database or adopt a
database that has recorded every pre-squash migration. It deliberately rejects
existing application tables without that complete history, as well as partial
pre-squash histories. Back up the database and investigate its migration records
instead of bypassing this guard.

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
`APP_TEST_RESET_LINKS`, `EVENTS_UPCOMING_LIMIT`, and
`PASSWORD_RESET_MAILER=aruba` unless a future workflow override is added.
Startup requires `APP_URL`, all four `DB_*` settings, `ADMIN_USER`,
`ADMIN_PASS_HASH`, `PASSWORD_RESET_MAILER`, `MAIL_FROM_ADDRESS`, all four
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
deployed application environment. For the deployment migration job,
`MIGRATION_DB_HOST`, `MIGRATION_DB_NAME`, and `MIGRATION_DB_USER` are optional
GitHub environment variables, and `MIGRATION_DB_PASS` is an optional secret.
When they are absent, the job falls back to `DB_*`. Provision a separate
least-privilege migration account before revoking DDL privileges from the
runtime `DB_USER`.

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
Then request one reset for a synthetic club and confirm delivery, sender,
one-hour expiry, and one-time use. A transport failure is logged as
`club.password_reset_delivery_failed` while the public response stays generic.

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

The FTP action uses a separate state file in each environment. Normal app sync
removes code files that were present in the preceding artifact but are absent
from the new one. The application sync still excludes `.env`, while a dedicated
runtime-config sync uploads only the generated `.env`. `dangerous-clean-slate`
remains disabled: runtime uploads/logs that never enter either sync state and
the independent `legacy/` directory are preserved. Do not delete deployment
state files manually, because doing so disables reliable stale-code retirement.

Repository administrators own rollback execution. Record the last healthy SHA
after each deployment. If health verification fails:

1. Do not rerun the failed SHA and do not reverse database migrations.
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
