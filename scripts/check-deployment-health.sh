#!/usr/bin/env bash
set -euo pipefail

if (( $# != 2 )); then
  echo "Usage: bash scripts/check-deployment-health.sh <https-url> <expected-commit-sha>" >&2
  exit 2
fi

HEALTH_URL="$1"
EXPECTED_REVISION="${2,,}"
if [[ ! "$HEALTH_URL" =~ ^https://[^[:space:]]+$ ]]; then
  echo "Deployment health URL must use HTTPS." >&2
  exit 2
fi
if [[ ! "$EXPECTED_REVISION" =~ ^[a-f0-9]{40}$ ]]; then
  echo "Expected deployment revision must be a complete Git commit SHA." >&2
  exit 2
fi

BODY_FILE="$(mktemp)"
trap 'rm -f "$BODY_FILE"' EXIT
LAST_STATUS="unavailable"

for attempt in 1 2 3 4 5 6; do
  separator='?'
  if [[ "$HEALTH_URL" == *\?* ]]; then
    separator='&'
  fi
  request_url="${HEALTH_URL}${separator}deployment_revision=${EXPECTED_REVISION}&attempt=${attempt}"
  LAST_STATUS="$(curl \
    --silent \
    --show-error \
    --location \
    --proto '=https' \
    --proto-redir '=https' \
    --max-time 15 \
    --output "$BODY_FILE" \
    --write-out '%{http_code}' \
    "$request_url" || true)"

  if [[ "$LAST_STATUS" == '200' ]]; then
    ACTUAL_REVISION="$(php -r '
      $data = json_decode((string) file_get_contents($argv[1]), true);
      if (!is_array($data) || ($data["status"] ?? null) !== "ok") {
          exit(1);
      }
      $revision = $data["revision"] ?? null;
      if (!is_string($revision) || preg_match("/\\A[a-f0-9]{40}\\z/", $revision) !== 1) {
          exit(1);
      }
      echo $revision;
    ' "$BODY_FILE" || true)"
    if [[ "$ACTUAL_REVISION" == "$EXPECTED_REVISION" ]]; then
      echo "Deployment health verified at revision $EXPECTED_REVISION."
      exit 0
    fi
  fi

  if (( attempt < 6 )); then
    sleep 5
  fi
done

echo "Deployment health verification failed (last HTTP status: $LAST_STATUS)." >&2
exit 1
