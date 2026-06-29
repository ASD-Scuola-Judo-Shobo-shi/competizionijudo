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
  --exclude="/scripts/***" \
  --include="/var/" \
  --include="/var/cache/" \
  --include="/var/cache/.gitkeep" \
  --exclude="/var/cache/***" \
  --include="/var/log/" \
  --include="/var/log/.gitkeep" \
  --exclude="/var/log/***" \
  --exclude="/var/***" \
  --exclude="*" \
  "${ROOT_DIR}/" "${BUILD_DIR}/"

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
  "${BUILD_DIR}/var/cache" \
  "${BUILD_DIR}/var/log"

bash "${ROOT_DIR}/scripts/verify-deploy-artifact.sh" "${BUILD_DIR}"
