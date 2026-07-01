#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="${ROOT_DIR}/build/deploy"

rm -rf "${BUILD_DIR}"
mkdir -p "${BUILD_DIR}"

rsync -a --no-owner --no-group \
  --exclude="*.bak" \
  --exclude="*~" \
  --exclude="*.swp" \
  --exclude=".DS_Store" \
  --include="/.htaccess" \
  --include="/LICENSE" \
  --include="/composer.json" \
  --include="/composer.lock" \
  --include="/config/***" \
  --include="/public/uploads/" \
  --include="/public/uploads/events/" \
  --include="/public/uploads/events/.htaccess" \
  --exclude="/public/uploads/***" \
  --include="/public/***" \
  --include="/routes/***" \
  --include="/src/***" \
  --include="/views/***" \
  --include="/lang/***" \
  --include="/migrations/***" \
  --include="/scripts/" \
  --include="/scripts/run-migrations.php" \
  --include="/scripts/purge-expired-data.php" \
  --exclude="/scripts/***" \
  --include="/var/" \
  --include="/var/log/" \
  --include="/var/log/.gitkeep" \
  --exclude="/var/log/***" \
  --exclude="/var/***" \
  --exclude="*" \
  "${ROOT_DIR}/" "${BUILD_DIR}/"

BUILD_REVISION="${BUILD_REVISION:-}"
if [[ -z "$BUILD_REVISION" ]]; then
  BUILD_REVISION="$(git -C "$ROOT_DIR" rev-parse HEAD)"
fi
if [[ ! "$BUILD_REVISION" =~ ^[a-fA-F0-9]{40}$ ]]; then
  echo "BUILD_REVISION must be a complete Git commit SHA." >&2
  exit 1
fi
printf '%s\n' "${BUILD_REVISION,,}" > "${BUILD_DIR}/REVISION"

COMPOSER_CACHE_DIR="${COMPOSER_CACHE_DIR:-${ROOT_DIR}/.cache/composer}" \
  composer install \
    --working-dir="${BUILD_DIR}" \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

find "${BUILD_DIR}" -type d -exec chmod 755 {} +
find "${BUILD_DIR}" -type f -exec chmod 644 {} +
chmod 775 \
  "${BUILD_DIR}/public/uploads/events" \
  "${BUILD_DIR}/var/log"

bash "${ROOT_DIR}/scripts/verify-deploy-artifact.sh" "${BUILD_DIR}"
