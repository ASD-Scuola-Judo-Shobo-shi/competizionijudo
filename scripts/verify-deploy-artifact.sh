#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$BASH_SOURCE")/.." && pwd)"
ARTIFACT_DIR="$ROOT_DIR/build/deploy"
if (( $# > 0 )); then
  ARTIFACT_DIR="$1"
fi

for path in \
  .htaccess \
  LICENSE \
  REVISION \
  composer.json \
  composer.lock \
  config/app.php \
  config/privacy.php \
  lang/en.php \
  lang/it.php \
  migrations/20260630_000000_create_schema.sql \
  public/index.php \
  public/assets/competizioni-judo-logo-optim.svgz \
  public/uploads/events/.htaccess \
  routes/web.php \
  scripts/run-migrations.php \
  scripts/purge-expired-data.php \
  src/bootstrap.php \
  vendor/autoload.php \
  views/layouts/app.php \
  var/log/.gitkeep; do
  if [[ ! -e "$ARTIFACT_DIR/$path" ]]; then
    echo "Missing required artifact path: $path" >&2
    exit 1
  fi
done

revision="$(tr -d '\r\n' < "$ARTIFACT_DIR/REVISION")"
if [[ ! "$revision" =~ ^[a-f0-9]{40}$ ]]; then
  echo "Artifact REVISION is not a complete lowercase Git commit SHA." >&2
  exit 1
fi

for path in \
  .env \
  .env.dev \
  .env.dev.example \
  docs \
  tests \
  scripts/build-deploy.sh \
  scripts/test-migrations.php \
  scripts/verify-deploy-artifact.sh \
  var/cache \
  vendor/bin/phpunit \
  vendor/bin/phpstan \
  vendor/bin/phpcs \
  vendor/phpunit \
  vendor/phpstan \
  vendor/squizlabs; do
  if [[ -e "$ARTIFACT_DIR/$path" ]]; then
    echo "Forbidden artifact path present: $path" >&2
    exit 1
  fi
done

if IFS= read -r _ < <(
  find "$ARTIFACT_DIR" -type f \
    \( -name '*.bak' -o -name '*~' -o -name '*.swp' -o -name '.DS_Store' \) \
    -print -quit
); then
  echo "Backup/editor files are present in the artifact." >&2
  exit 1
fi

if IFS= read -r _ < <(
  find "$ARTIFACT_DIR/public/uploads" -type f ! -name '.htaccess' -print -quit
); then
  echo "Runtime upload contents are present in the artifact." >&2
  exit 1
fi

for directory in \
  "$ARTIFACT_DIR/public/uploads/events" \
  "$ARTIFACT_DIR/var/log"; do
  if [[ ! -d "$directory" || ! -w "$directory" ]]; then
    echo "Writable runtime directory is unavailable: $directory" >&2
    exit 1
  fi
done

echo "Deployment artifact manifest verified."
