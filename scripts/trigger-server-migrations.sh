#!/usr/bin/env bash
set -euo pipefail

: "${MIGRATION_ENDPOINT_URL:?MIGRATION_ENDPOINT_URL is required}"
: "${MIGRATIONS_TOKEN:?MIGRATIONS_TOKEN is required}"

if [[ ! "$MIGRATION_ENDPOINT_URL" =~ ^https://[^[:space:]/?#]+(/[^[:space:]?#]*)?$ ]]; then
  echo 'MIGRATION_ENDPOINT_URL must be an HTTPS URL without a query string or fragment.' >&2
  exit 2
fi

if [[ "$MIGRATIONS_TOKEN" == *$'\n'* || "$MIGRATIONS_TOKEN" == *$'\r'* ]]; then
  echo 'MIGRATIONS_TOKEN must not contain a line break.' >&2
  exit 2
fi

response_file="$(mktemp)"
cleanup() {
  rm -f "$response_file"
}
trap cleanup EXIT

http_status=''
if ! http_status="$(curl \
  --fail-with-body \
  --silent \
  --show-error \
  --retry 2 \
  --retry-all-errors \
  --connect-timeout 10 \
  --max-time 120 \
  --output "$response_file" \
  --write-out '%{http_code}' \
  --request POST \
  --header "X-Migration-Token: ${MIGRATIONS_TOKEN}" \
  --header 'Content-Length: 0' \
  "$MIGRATION_ENDPOINT_URL")"; then
  cat "$response_file" >&2
  exit 1
fi

if [[ "$http_status" != '200' ]] || [[ "$(<"$response_file")" != '{"status":"ok"}' ]]; then
  echo "Migration endpoint returned unexpected HTTP ${http_status:-status} response." >&2
  cat "$response_file" >&2
  exit 1
fi
