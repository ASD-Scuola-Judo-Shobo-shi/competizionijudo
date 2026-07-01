#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ARTIFACT_DIR="${1:-${ROOT_DIR}/build/deploy}"
BASE_PORT="${DEPLOY_SMOKE_PORT:-$((18000 + ($$ % 1000)))}"
SERVER_PIDS=()
TEMP_DIR="$(mktemp -d)"

cleanup() {
  for pid in "${SERVER_PIDS[@]}"; do
    kill "$pid" 2>/dev/null || true
    wait "$pid" 2>/dev/null || true
  done
  rm -rf "$TEMP_DIR"
}
trap cleanup EXIT

bash "${ROOT_DIR}/scripts/verify-deploy-artifact.sh" "$ARTIFACT_DIR"

for route in \
  / \
  /index.php \
  /about \
  /privacy \
  /health \
  /club_register.php \
  /club_login.php \
  /club_forgot_password.php \
  /club_reset_password.php \
  /clubs.php \
  /events.php \
  /event_details.php \
  /event_entries.php \
  /event_register.php \
  /language/switch; do
  if ! grep -Fq "\$router->get('${route}'" "$ARTIFACT_DIR/routes/web.php"; then
    echo "Missing expected public GET route: $route" >&2
    exit 1
  fi
done

boot_and_request() {
  local locale="$1"
  local port="$2"
  local expected="$3"
  local expected_privacy="$4"
  local body_file="${TEMP_DIR}/${locale}.html"
  local privacy_body_file="${TEMP_DIR}/${locale}-privacy.html"
  local logo_headers_file="${TEMP_DIR}/${locale}-logo-headers.txt"
  local log_file="${TEMP_DIR}/${locale}.log"
  local status=''

  (
    cd "$ARTIFACT_DIR"
    APP_ENV=production APP_DEBUG=false APP_LOCALE="$locale" \
      APP_URL='https://smoke.example.test' \
      DB_HOST=127.0.0.1 DB_NAME=synthetic_smoke DB_USER=synthetic_smoke \
      DB_PASS=synthetic-smoke-password ADMIN_USER=synthetic-admin \
      ADMIN_PASS_HASH=synthetic-smoke-password-hash \
      PASSWORD_RESET_MAILER=aruba MAIL_FROM_ADDRESS='postmaster@example.test' \
      APP_OWNER='Synthetic Controller' APP_OWNER_ADDRESS='1 Test Street' \
      APP_OWNER_FISCAL_CODE='SYNTHETIC-FISCAL-CODE' \
      APP_OWNER_EMAIL='privacy@example.test' \
      APP_WEBHOST='Synthetic Hosting' APP_WEBHOST_LOCATION='European Union' \
      APP_LOG_RETENTION_DAYS=30 APP_BACKUP_RETENTION_DAYS=30 \
      php -d display_errors=0 -S "127.0.0.1:${port}" -t public public/index.php
  ) >"$log_file" 2>&1 &
  local server_pid=$!
  SERVER_PIDS+=("$server_pid")

  for _ in {1..30}; do
    if ! kill -0 "$server_pid" 2>/dev/null; then
      echo "Artifact server stopped before the ${locale} request." >&2
      sed -n '1,120p' "$log_file" >&2
      exit 1
    fi

    status="$(curl --silent --output "$body_file" --write-out '%{http_code}' \
      "http://127.0.0.1:${port}/about" || true)"
    if [[ "$status" == '200' ]]; then
      break
    fi
    sleep 0.1
  done

  if [[ "$status" != '200' ]]; then
    echo "Artifact /about returned HTTP ${status:-unavailable} for locale ${locale}." >&2
    sed -n '1,120p' "$log_file" >&2
    exit 1
  fi

  if ! grep -Fq "$expected" "$body_file"; then
    echo "Artifact /about did not render the expected ${locale} translation." >&2
    exit 1
  fi

  if grep -Eiq 'stack trace|fatal error|uncaught exception|xdebug' "$body_file"; then
    echo "Artifact response exposed debug output for locale ${locale}." >&2
    exit 1
  fi

  status="$(curl --silent --output "$privacy_body_file" --write-out '%{http_code}' \
    "http://127.0.0.1:${port}/privacy" || true)"
  if [[ "$status" != '200' ]] \
    || ! grep -Fq 'Synthetic Controller' "$privacy_body_file" \
    || ! grep -Fq "$expected_privacy" "$privacy_body_file"; then
    echo "Artifact privacy notice did not render configured controller data for locale ${locale}." >&2
    exit 1
  fi

  if grep -Fq 'cookie-consent' "$privacy_body_file"; then
    echo "Artifact privacy notice included the obsolete consent banner." >&2
    exit 1
  fi

  status="$(curl --silent --output /dev/null --dump-header "$logo_headers_file" --write-out '%{http_code}' \
    "http://127.0.0.1:${port}/assets/competizioni-judo-logo-optim.svgz" || true)"
  if [[ "$status" != '200' ]] \
    || ! grep -Eiq '^Content-Type: image/svg\+xml[[:space:]]*$' "$logo_headers_file" \
    || ! grep -Eiq '^Content-Encoding: gzip[[:space:]]*$' "$logo_headers_file"; then
    echo "Artifact SVGZ logo was not served with the required type and gzip encoding." >&2
    exit 1
  fi
}

boot_and_request it "$BASE_PORT" 'Una compatta applicazione PHP MVC' 'Origine dei dati'
boot_and_request en "$((BASE_PORT + 1))" 'A compact PHP MVC application' 'Source of the data'

echo "Deployment artifact boot verified for Italian and English."
