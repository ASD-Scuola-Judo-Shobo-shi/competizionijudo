#!/usr/bin/env sh
set -eu

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
cd "$ROOT_DIR"

ACTIONLINT_VERSION="1.7.7"

if ! command -v go >/dev/null 2>&1; then
  echo "workflow check: Go is required to install actionlint." >&2
  exit 1
fi

actionlint_path="${ACTIONLINT_BIN:-$(go env GOPATH)/bin/actionlint}"

if ! "$actionlint_path" -version 2>/dev/null | grep -Fqx "v${ACTIONLINT_VERSION}"; then
  if [ -n "${ACTIONLINT_BIN:-}" ]; then
    echo "workflow check: ACTIONLINT_BIN must point to actionlint v${ACTIONLINT_VERSION}." >&2
    exit 1
  fi

  echo "workflow check: installing actionlint v${ACTIONLINT_VERSION}"
  go install "github.com/rhysd/actionlint/cmd/actionlint@v${ACTIONLINT_VERSION}"
fi

echo "workflow check: linting GitHub Actions workflows"
"$actionlint_path"
