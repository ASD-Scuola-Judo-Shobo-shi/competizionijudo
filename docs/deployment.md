# Deployment

GitHub Actions builds a production-only application artifact and synchronizes it
to the branch-specific FTP directory. The artifact never contains `.env`, and
the FTP jobs explicitly exclude `.env`, so deployments preserve the copy owned
by the server operator.

## First-time environment provisioning

Before directing traffic to a new `prod/` or `dev/` directory, an authorized
hosting operator must:

1. Copy `.env.example` to `.env` through the hosting control panel or another
   approved encrypted administrative channel. Do not place it in a Git commit,
   workflow artifact, ticket, or chat message.
2. Replace every blank required value and verify the `APP_OWNER*`,
   `APP_WEBHOST*`, and retention facts displayed by the privacy notice. Use a dedicated least-privilege database
   account and a password hash produced with PHP's `password_hash()` for
   `ADMIN_PASS_HASH`; never store the administrator's plaintext password.
   Set `PASSWORD_RESET_MAILER=aruba` and use the domain's approved postmaster
   address for `MAIL_FROM_ADDRESS`.
3. Set `APP_ENV=production`, `APP_DEBUG=false`, and the canonical HTTPS
   `APP_URL` for production. Development may use `APP_ENV=development`, but it
   must use separate database and administrator credentials.
4. Restrict `.env` permissions to the hosting account and PHP runtime. On hosts
   that support Unix modes, use `0600`, or `0640` with a dedicated runtime
   group. Confirm that web requests cannot retrieve dotfiles.
5. Run `php scripts/run-migrations.php` from the deployed application directory,
   then perform the documented deployment smoke check before enabling traffic.

The consolidated schema baseline can initialize an empty database or adopt a
database that has recorded every pre-squash migration. It deliberately rejects
existing application tables without that complete history, as well as partial
pre-squash histories. Back up the database and investigate its migration records
instead of bypassing this guard.

The deployment workflow does not create or update `.env`. Rotating credentials
is therefore a server-side operation and does not require rebuilding the
artifact. Operations must assign the named owner and secure provisioning channel
for each hosting environment before its first deployment.

## Required application settings

`.env.example` is the authoritative non-secret inventory. Production startup
requires `APP_URL`, all four `DB_*` settings, `ADMIN_USER`, `ADMIN_PASS_HASH`,
`PASSWORD_RESET_MAILER`, `MAIL_FROM_ADDRESS`, all four `APP_OWNER*` settings,
both `APP_WEBHOST*` settings, and positive `APP_LOG_RETENTION_DAYS` and
`APP_BACKUP_RETENTION_DAYS` values. It validates the public owner contact email.
The controller must verify the published facts and application-owned legal text
before deployment and update the notice if the actual processing changes.

If required production configuration is missing, startup returns a server error
without exposing values. The operator should inspect `var/log/application.log`;
events such as `configuration.missing.db_name` identify the setting to provision,
while exception messages and configuration values remain redacted.

The `MIGRATION_TEST_*` variables documented in `.env.example` belong only to the
isolated local/CI migration smoke harness. Do not provision them in a deployed
application environment.

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

The FTP action uses a separate state file in each environment. Normal sync
removes code files that were present in the preceding artifact but are absent
from the new one. `dangerous-clean-slate` remains disabled: server-owned `.env`,
runtime uploads/logs that never enter the artifact state, and the independent
`legacy/` directory are preserved. Do not delete either deployment state file
manually, because doing so disables reliable stale-code retirement.

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
