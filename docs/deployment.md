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
2. Replace every blank required value. Use a dedicated least-privilege database
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
requires `APP_URL`, all four `DB_*` settings, `ADMIN_USER`, and
`ADMIN_PASS_HASH`. It also rejects an enabled or malformed `APP_DEBUG` value.
Optional settings retain the defaults shown in the example.

If required production configuration is missing, startup returns a server error
without exposing values. The operator should inspect `var/log/application.log`;
events such as `configuration.missing.db_name` identify the setting to provision,
while exception messages and configuration values remain redacted.

The `MIGRATION_TEST_*` variables documented in `.env.example` belong only to the
isolated local/CI migration smoke harness. Do not provision them in a deployed
application environment.
