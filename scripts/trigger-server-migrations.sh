#!/usr/bin/env bash
set -euo pipefail

: "${MIGRATION_ENDPOINT_URL:?MIGRATION_ENDPOINT_URL is required}"
: "${MIGRATION_WEBHOOK_SECRET:?MIGRATION_WEBHOOK_SECRET is required}"

if [[ ! "$MIGRATION_ENDPOINT_URL" =~ ^https://[^[:space:]/?#]+(?:/[^[:space:]?#]*)?$ ]]; then
  echo 'MIGRATION_ENDPOINT_URL must be an HTTPS URL without a query string or fragment.' >&2
  exit 2
fi

timestamp="$(date +%s)"
signature="$({ printf 'competizionijudo-migration-v1|%s' "$timestamp"; } | openssl dgst -sha256 -hmac "$MIGRATION_WEBHOOK_SECRET" -hex | awk '{print $NF}')"

curl \
  --fail-with-body \
  --silent \
  --show-error \
  --retry 2 \
  --retry-all-errors \
  --connect-timeout 10 \
  --max-time 120 \
  --request POST \
  --header "X-Migration-Timestamp: ${timestamp}" \
  --header "X-Migration-Signature: ${signature}" \
  --header 'Content-Length: 0' \
  "$MIGRATION_ENDPOINT_URL"
