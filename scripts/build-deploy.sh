#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="${ROOT_DIR}/build/deploy"

rm -rf "${BUILD_DIR}"
mkdir -p "${BUILD_DIR}"

rsync -a \
  --include="/.htaccess" \
  --include="/composer.json" \
  --include="/config/***" \
  --include="/public/***" \
  --include="/routes/***" \
  --include="/src/***" \
  --include="/vendor/***" \
  --include="/views/***" \
  --include="/var/" \
  --include="/var/cache/" \
  --include="/var/cache/.gitkeep" \
  --include="/var/log/" \
  --include="/var/log/.gitkeep" \
  --exclude="*" \
  "${ROOT_DIR}/" "${BUILD_DIR}/"

find "${BUILD_DIR}" -type d -exec chmod 755 {} +
find "${BUILD_DIR}" -type f -exec chmod 644 {} +
