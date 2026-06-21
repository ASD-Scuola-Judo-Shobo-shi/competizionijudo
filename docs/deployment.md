# Deployment

This project deploys from GitHub Actions over FTP/FTPS to shared hosting providers such as Aruba or Altervista.

## Branch Targets

| Branch | Remote subdirectory | Public URL |
| --- | --- | --- |
| `main` | `prod/` | `https://www.competizionijudo.it` |
| `dev` | `dev/` | `https://dev.competizionijudo.it` |

Configure the hosting control panel so:

- `www.competizionijudo.it` points to the `prod` directory.
- `dev.competizionijudo.it` points to the `dev` directory.

If the provider cannot point the domain directly at those directories, upload the generated `prod` and `dev` folders under the web root and use the included `.htaccess` files to route requests into each folder's `public/` front controller.

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
| `FTP_PROD_DIR` | `/prod/` | Remote directory for the `main` branch |
| `FTP_DEV_DIR` | `/dev/` | Remote directory for the `dev` branch |
| `APP_NAME` | `Competizioni Judo` | Display name written into the deployed `.env` |

Remote FTP paths are provider-specific. Common examples are `/prod/`, `/htdocs/prod/`, `/www.competizionijudo.it/prod/`, or `/public_html/prod/`.

## What Gets Uploaded

The workflow builds a clean `build/deploy` directory containing only:

- `.htaccess`
- `composer.json`
- `config/`
- `public/`
- `routes/`
- `src/`
- `vendor/`
- `views/`
- `var/cache/.gitkeep`
- `var/log/.gitkeep`

It intentionally excludes tests, local tooling, docs, legacy files, Git metadata, and development dependencies.

## First Deployment

1. Set the DNS records for `www` and `dev` to the hosting provider.
2. Configure the hosting panel document roots:
   - `www.competizionijudo.it` -> `prod`
   - `dev.competizionijudo.it` -> `dev`
3. Add the GitHub secrets and variables.
4. Push to `dev` to deploy the development site.
5. Push or merge to `main` to deploy production.

## PHP Version

Set the hosting PHP version to PHP 8.2 or newer.
