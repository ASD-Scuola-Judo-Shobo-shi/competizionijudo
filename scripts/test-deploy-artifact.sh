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
  local body_file="${TEMP_DIR}/${locale}.html"
  local log_file="${TEMP_DIR}/${locale}.log"
  local status=''

  (
    cd "$ARTIFACT_DIR"
    APP_ENV=production APP_DEBUG=false APP_LOCALE="$locale" \
      APP_URL='https://smoke.example.test' \
      DB_HOST=127.0.0.1 DB_NAME=synthetic_smoke DB_USER=synthetic_smoke \
      DB_PASS=synthetic-smoke-password ADMIN_USER=synthetic-admin \
      ADMIN_PASS_HASH=synthetic-smoke-password-hash \
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
}

boot_and_request it "$BASE_PORT" 'Una compatta applicazione PHP MVC'
boot_and_request en "$((BASE_PORT + 1))" 'A compact PHP MVC application'

echo "Deployment artifact boot verified for Italian and English."
