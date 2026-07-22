#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

echo 'CI: running quality and deployment-artifact lanes in parallel'
composer ci:quality &
quality_pid=$!
composer deploy:preflight &
artifact_pid=$!

status=0
if ! wait "$quality_pid"; then
  status=1
fi
if ! wait "$artifact_pid"; then
  status=1
fi

exit "$status"
