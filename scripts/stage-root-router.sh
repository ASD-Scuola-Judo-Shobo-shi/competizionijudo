#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE_PATH="${ROOT_DIR}/.htaccess"
STAGING_DIR="${1:-${ROOT_DIR}/build/root-htaccess}"

if [[ ! -s "$SOURCE_PATH" ]]; then
  echo "Required repository root .htaccess is missing or empty." >&2
  exit 1
fi

rm -rf "$STAGING_DIR"
mkdir -p "$STAGING_DIR"
cp "$SOURCE_PATH" "$STAGING_DIR/.htaccess"

if ! cmp --silent "$SOURCE_PATH" "$STAGING_DIR/.htaccess"; then
  echo "Staged root .htaccess does not match the repository source." >&2
  exit 1
fi

(
  cd "$STAGING_DIR"
  sha256sum .htaccess > htaccess.sha256
  sha256sum --check --strict htaccess.sha256
)

echo "Repository root .htaccess staged and verified."
