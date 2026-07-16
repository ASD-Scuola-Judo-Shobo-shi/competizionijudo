#!/usr/bin/env bash
set -euo pipefail

operation="${1:?operation required}"
remote_dir="${2:?remote directory required}"
: "${FTP_SERVER:?FTP_SERVER is required}" "${FTP_PORT:?FTP_PORT is required}" \
  "${FTP_USERNAME:?FTP_USERNAME is required}" "${FTP_PASSWORD:?FTP_PASSWORD is required}"

echo "FTPS upload wrapper v2: preflighting ${FTP_SERVER}:${FTP_PORT}."

[[ "$FTP_SERVER" =~ ^[A-Za-z0-9.-]+$ ]] || { echo 'Invalid FTP_SERVER' >&2; exit 2; }
[[ "$FTP_PORT" =~ ^[0-9]{1,5}$ ]] || { echo 'Invalid FTP_PORT' >&2; exit 2; }
[[ "$remote_dir" =~ ^[A-Za-z0-9_./-]+$ ]] || { echo 'Invalid remote directory' >&2; exit 2; }
[[ "$FTP_USERNAME" != *$'\n'* && "$FTP_USERNAME" != *$'\r'* ]] \
  || { echo 'FTP_USERNAME must not contain line breaks.' >&2; exit 2; }
[[ "$FTP_PASSWORD" != *$'\n'* && "$FTP_PASSWORD" != *$'\r'* ]] \
  || { echo 'FTP_PASSWORD must not contain line breaks; recreate the GitHub secret.' >&2; exit 2; }

lftp_quote() {
  local value="$1"
  [[ "$value" != *$'\n'* && "$value" != *$'\r'* && "$value" != *$'\0'* ]] || return 1
  value="${value//\\/\\\\}"
  value="${value//\"/\\\"}"
  printf '"%s"' "$value"
}

case "$operation" in
  deploy) commands="mkdir -p $(lftp_quote "$remote_dir"); cd $(lftp_quote "$remote_dir"); rm -rf vendor/; mirror -R --exclude-glob .git* --exclude-glob .env* --exclude-glob legacy/ build/deploy/ .;" ;;
  env) commands="mkdir -p $(lftp_quote "$remote_dir"); cd $(lftp_quote "$remote_dir"); mirror -R build/runtime-env/ .;" ;;
  root) commands="mirror -R --no-perms build/root-router/ $(lftp_quote "$remote_dir");" ;;
  *) echo 'Unknown upload operation' >&2; exit 2 ;;
esac

connection_settings="set cmd:fail-exit true; set net:max-retries 2; set net:timeout 30; set ftp:ssl-allow true; set ftp:ssl-force true; set ftp:ssl-protect-data true; set ssl:verify-certificate true;"
open_command="open -p $(lftp_quote "$FTP_PORT") -u $(lftp_quote "$FTP_USERNAME"),$(lftp_quote "$FTP_PASSWORD") $(lftp_quote "$FTP_SERVER");"

if ! lftp -c "${connection_settings} ${open_command} pwd;"; then
  echo "FTPS connection, certificate, or authentication failed for ${FTP_SERVER}:${FTP_PORT}." >&2
  exit 1
fi

echo "FTPS preflight succeeded; starting ${operation} upload."
if ! lftp -c "${connection_settings} ${open_command} ${commands}"; then
  echo "FTPS upload failed after a successful preflight; inspect remote directory permissions and available quota." >&2
  exit 1
fi
