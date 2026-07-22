#!/usr/bin/env bash
set -euo pipefail

: "${MIGRATION_ENDPOINT_URL:?MIGRATION_ENDPOINT_URL is required}"
: "${MIGRATION_BASIC_AUTH_USER:?MIGRATION_BASIC_AUTH_USER is required}"

if [[ ! "$MIGRATION_ENDPOINT_URL" =~ ^https://[^[:space:]/?#]+(/[^[:space:]?#]*)?$ ]]; then
  echo 'MIGRATION_ENDPOINT_URL must be an HTTPS URL without a query string or fragment.' >&2
  exit 2
fi

if [[ "$MIGRATION_BASIC_AUTH_USER" == *$'\n'* || "$MIGRATION_BASIC_AUTH_USER" == *$'\r'* || "$MIGRATION_BASIC_AUTH_USER" == *:* ]]; then
  echo 'MIGRATION_BASIC_AUTH_USER must not contain a colon or a line break.' >&2
  exit 2
fi

curl \
  --fail-with-body \
  --silent \
  --show-error \
  --retry 2 \
  --retry-all-errors \
  --connect-timeout 10 \
  --max-time 120 \
  --basic \
  --user "${MIGRATION_BASIC_AUTH_USER}:${MIGRATION_BASIC_AUTH_USER}" \
  --request POST \
  --header 'Content-Length: 0' \
  "$MIGRATION_ENDPOINT_URL"
