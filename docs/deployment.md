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
2. Replace every blank required value, including the `PRIVACY_*` facts required
   for the deployed privacy notice. Use a dedicated least-privilege database
   account and a password hash produced with PHP's `password_hash()` for
   `ADMIN_PASS_HASH`; never store the administrator's plaintext password.
3. Set `APP_ENV=production`, `APP_DEBUG=false`, and the canonical HTTPS
   `APP_URL` for production. Development may use `APP_ENV=development`, but it
   must use separate database and administrator credentials.
4. Restrict `.env` permissions to the hosting account and PHP runtime. On hosts
   that support Unix modes, use `0600`, or `0640` with a dedicated runtime
   group. Confirm that web requests cannot retrieve dotfiles.
5. Run `php scripts/run-migrations.php` from the deployed application directory,
   then perform the documented deployment smoke check before enabling traffic.

The deployment workflow does not create or update `.env`. Rotating credentials
is therefore a server-side operation and does not require rebuilding the
artifact. Operations must assign the named owner and secure provisioning channel
for each hosting environment before its first deployment.

## Required application settings

`.env.example` is the authoritative non-secret inventory. Production startup
requires `APP_URL`, all four `DB_*` settings, `ADMIN_USER`, `ADMIN_PASS_HASH`,
and the non-optional `PRIVACY_*` settings. It validates privacy contact email
and requires positive log and backup retention periods. `PRIVACY_DPO_EMAIL` and
`PRIVACY_ADDITIONAL_PROCESSORS` are optional only when they do not apply. The
controller must verify that all published facts and legal bases are accurate;
application defaults cannot determine them.

If required production configuration is missing, startup returns a server error
without exposing values. The operator should inspect `var/log/application.log`;
events such as `configuration.missing.db_name` identify the setting to provision,
while exception messages and configuration values remain redacted.

The `MIGRATION_TEST_*` variables documented in `.env.example` belong only to the
isolated local/CI migration smoke harness. Do not provision them in a deployed
application environment.

## Runtime data and privacy retention

`public/uploads/events/`, `var/log/`, the database, and backups are runtime data
owned by the server operator. Code artifacts contain only the upload directory's
access-control file and must never overwrite or synchronize runtime upload
contents. The application deletes event documents when they are replaced and
when their event is deleted.

Schedule `composer privacy:purge` at least daily. It deletes closed-event entry
snapshots older than one year, enforcing a one-year maximum; monitor its exit status. Configure log rotation
to delete application logs after `PRIVACY_LOG_RETENTION_DAYS`, and configure the
backup system to delete backups after `PRIVACY_BACKUP_RETENTION_DAYS`. Those two
host-level policies are not implemented by the PHP process. Test both restores
and expiry, and document any processor that can access backups or logs.

Before going live, the controller must also establish procedures for data-subject
requests and breaches, confirm club authority for athletes and minors, sign the
required processor agreements, and verify that transfers match
`PRIVACY_DATA_TRANSFER_DETAILS`. The public notice is at `/privacy`.

The operational baseline should be reviewed against the official
[GDPR text](https://eur-lex.europa.eu/eli/reg/2016/679/2016-05-04/eng), the
[EDPB privacy-by-design guidance](https://www.edpb.europa.eu/topics/ai-and-technology/privacy-by-design-and-by-default_en),
and the [Italian authority's cookie guidance](https://www.garanteprivacy.it/web/guest/home/docweb/-/docweb-display/docweb/9677876).
These application notes are not a substitute for the controller's legal and
organizational assessment.
