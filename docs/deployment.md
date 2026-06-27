# Deployment

This project deploys from GitHub Actions over FTP/FTPS to shared hosting providers such as Aruba or Altervista.

## Branch Targets

| Branch | Environment | Remote subdirectory | Public URL |
| --- | --- | --- | --- |
| `main` | Production | `prod/` | `https://www.competizionijudo.it` |
| `dev` | Development | `dev/` | `https://dev.competizionijudo.it` |

Configure the hosting control panel so:

- `www.competizionijudo.it` points to the `prod` directory.
- `dev.competizionijudo.it` points to the `dev` directory.

If the provider cannot point the domain directly at those directories, the deploy workflow also uploads a root `.htaccess` to the FTP root that rewrites traffic into the appropriate subdirectory.

## GitHub Secrets

Create these repository or environment secrets:

| Secret | Meaning |
| --- | --- |
| `FTP_SERVER` | FTP host, for example the Aruba or Altervista FTP server name |
| `FTP_USERNAME` | FTP username |
| `FTP_PASSWORD` | FTP password |

Use environment-specific secrets instead if production and development use different FTP accounts. In that case, define the same secret names in the GitHub `production` and `development` environments.

## GitHub Variables

Create these repository or environment variables:

| Variable | Default | Meaning |
| --- | --- | --- |
| `FTP_PROTOCOL` | `ftps` | Use `ftps` for Aruba when available; use `ftp` only if the host does not support FTPS |
| `FTP_PORT` | `21` | FTP port (typically 21 for FTP/FTPS, 990 for implicit FTPS) |
| `FTP_PROD_DIR` | `/prod/` | Remote directory for the `main` branch |
| `FTP_DEV_DIR` | `/dev/` | Remote directory for the `dev` branch |
| `APP_NAME` | `Competizioni Judo` | Display name written into the deployed `.env` |

Remote FTP paths are provider-specific. Common examples are `/prod/`, `/htdocs/prod/`, `/www.competizionijudo.it/prod/`, or `/public_html/prod/`.

## What Gets Uploaded

The workflow builds a clean `build/deploy` directory containing only:

- `.htaccess` (application-level, inside the subdirectory)
- `composer.json`
- `config/`
- `public/`
- `routes/`
- `src/`
- `vendor/`
- `views/`
- `var/cache/.gitkeep`
- `var/log/.gitkeep`
- `.env` (generated dynamically per environment)

It intentionally excludes tests, local tooling, docs, legacy files, Git metadata, and development dependencies.

Additionally, when deploying to production (`main` branch), a root `.htaccess` is uploaded separately to the FTP root (if `root.htaccess` exists in the repository). This file handles routing requests into the `prod/` subdirectory via Apache mod_rewrite, which is essential on shared hosting where the document root cannot be changed.

## First Deployment

1. Set the DNS records for `www` and `dev` to the hosting provider.
2. Configure the hosting panel document roots:
   - `www.competizionijudo.it` -> `prod`
   - `dev.competizionijudo.it` -> `dev`
3. Add the GitHub secrets and variables.
4. Push to `dev` to deploy the development site.
5. Push or merge to `main` to deploy production.

> **Note:** For providers like Aruba where the document root cannot be changed, the root `.htaccess` routing approach is used instead. The production deployment workflow uploads the `.htaccess` to the FTP root, which rewrites all web traffic into the `prod/` directory.

## PHP Version

Set the hosting PHP version to PHP 8.2 or newer.