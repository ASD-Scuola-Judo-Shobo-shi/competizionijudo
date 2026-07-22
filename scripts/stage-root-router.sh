#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
HTACCESS_SOURCE="${ROOT_DIR}/root.htaccess"
INDEX_SOURCE="${ROOT_DIR}/index.php"
STAGING_DIR="${1:-${ROOT_DIR}/build/root-router}"

if [[ ! -s "$HTACCESS_SOURCE" ]]; then
  echo "Required repository root.htaccess is missing or empty." >&2
  exit 1
fi

if [[ ! -s "$INDEX_SOURCE" ]]; then
  echo "Required repository index.php is missing or empty." >&2
  exit 1
fi

rm -rf "$STAGING_DIR"
mkdir -p "$STAGING_DIR"
cp "$HTACCESS_SOURCE" "$STAGING_DIR/.htaccess"
cp "$INDEX_SOURCE" "$STAGING_DIR/index.php"

if ! cmp --silent "$HTACCESS_SOURCE" "$STAGING_DIR/.htaccess"; then
  echo "Staged .htaccess does not match the repository source." >&2
  exit 1
fi

if ! cmp --silent "$INDEX_SOURCE" "$STAGING_DIR/index.php"; then
  echo "Staged index.php does not match the repository source." >&2
  exit 1
fi

(
  cd "$STAGING_DIR"
  sha256sum .htaccess index.php > router.sha256
  sha256sum --check --strict router.sha256
  rm router.sha256

  if [[ "$(find . -maxdepth 1 -type f | wc -l)" -ne 2 ]]; then
    echo "Root router staging directory must contain only .htaccess and index.php." >&2
    exit 1
  fi
)

echo "Repository root router (.htaccess + index.php) staged and verified."
