#!/usr/bin/env bash
set -euo pipefail

operation="${1:?operation required}"
remote_dir="${2:?remote directory required}"
deploy_artifact_dir="${DEPLOY_ARTIFACT_DIR:-build/deploy}"
: "${FTP_SERVER:?FTP_SERVER is required}" "${FTP_PORT:?FTP_PORT is required}" \
  "${FTP_USERNAME:?FTP_USERNAME is required}" "${FTP_PASSWORD:?FTP_PASSWORD is required}"

[[ "$FTP_SERVER" =~ ^[A-Za-z0-9.-]+$ ]] || { echo 'Invalid FTP_SERVER' >&2; exit 2; }
[[ "$FTP_PORT" =~ ^[0-9]{1,5}$ ]] || { echo 'Invalid FTP_PORT' >&2; exit 2; }
[[ "$remote_dir" =~ ^[A-Za-z0-9_./-]+$ ]] || { echo 'Invalid remote directory' >&2; exit 2; }
[[ "$FTP_USERNAME" != *$'\n'* && "$FTP_USERNAME" != *$'\r'* ]] \
  || { echo 'FTP_USERNAME must not contain line breaks.' >&2; exit 2; }
[[ "$FTP_PASSWORD" != *$'\n'* && "$FTP_PASSWORD" != *$'\r'* ]] \
  || { echo 'FTP_PASSWORD must not contain line breaks; recreate the GitHub secret.' >&2; exit 2; }

# Aruba's Linux FTP endpoint presents a certificate for its canonical host,
# while the customer-domain alias resolves to the same server without matching
# that certificate. Keep verification enabled and connect using the verified
# certificate identity for this known alias.
if [[ "$FTP_SERVER" == 'ftp.competizionijudo.it' ]]; then
  echo 'Using Aruba certificate hostname ftplnx02.aruba.it for the configured FTP alias.'
  FTP_SERVER='ftplnx02.aruba.it'
fi

echo "FTPS upload wrapper v6: preflighting ${FTP_SERVER}:${FTP_PORT}."

lftp_quote() {
  local value="$1"
  # Bash variables cannot contain NUL bytes; reject the representable control
  # characters here before quoting the value for lftp's command language.
  [[ "$value" != *$'\n'* && "$value" != *$'\r'* ]] || return 1
  value="${value//\\/\\\\}"
  value="${value//\"/\\\"}"
  printf '"%s"' "$value"
}

transfer_dir=''
verification_dir=''
cleanup() {
  if [[ -n "$transfer_dir" && -d "$transfer_dir" ]]; then
    rm -rf "$transfer_dir"
  fi
  if [[ -n "$verification_dir" && -d "$verification_dir" ]]; then
    rm -rf "$verification_dir"
  fi
}
trap cleanup EXIT

# Keep the FTPS data stream uncompressed. Aruba's FTP service has returned
# corrupt read-backs with MODE Z enabled, while ordinary binary transfers are
# covered by the checksum verification below.
connection_settings="set cmd:fail-exit true; set cmd:verify-path true; set net:max-retries 5; set net:timeout 45; set xfer:timeout 90; set xfer:use-temp-file true; set xfer:temp-file-name .deploying-*; set ftp:list-options -a; set ftp:rest-stor true; set ftp:use-mode-z false; set ftp:ssl-allow true; set ftp:ssl-force true; set ftp:ssl-protect-data true; set ssl:verify-certificate true;"
open_command="open -p $(lftp_quote "$FTP_PORT") -u $(lftp_quote "$FTP_USERNAME"),$(lftp_quote "$FTP_PASSWORD") $(lftp_quote "$FTP_SERVER");"

upload_commands() {
  case "$operation" in
    env)
      printf 'mkdir -pf %s; cd %s; mirror -R --transfer-all --no-perms --verbose --max-errors=1 build/runtime-env/ .;' \
        "$(lftp_quote "$remote_dir")" "$(lftp_quote "$remote_dir")"
      ;;
    root)
      printf 'mirror -R --transfer-all --no-perms --verbose --max-errors=1 build/root-router/ %s;' \
        "$(lftp_quote "$remote_dir")"
      ;;
    *)
      return 1
      ;;
  esac
}

upload_full_deployment_artifact() {
  local commands
  commands="mkdir -pf $(lftp_quote "$remote_dir"); cd $(lftp_quote "$remote_dir"); mirror -R --transfer-all --no-perms --verbose --max-errors=1 --exclude-glob .env* --exclude-glob legacy/ --exclude-glob DEPLOYMENT_MANIFEST.sha256 $(lftp_quote "${deploy_artifact_dir}/") .; put $(lftp_quote "${deploy_artifact_dir}/DEPLOYMENT_MANIFEST.sha256");"

  echo 'FTPS upload: no trusted compatible remote manifest; transferring the complete artifact.'
  if ! lftp -c "${connection_settings} ${open_command} ${commands}"; then
    echo 'FTPS artifact upload failed; inspect remote directory permissions and available quota.' >&2
    exit 1
  fi
}

manifest_checksum=''
manifest_relative_path=''
parse_manifest_record() {
  local record="$1"
  manifest_checksum="${record:0:64}"
  manifest_relative_path="${record:66}"

  [[ "$record" == "${manifest_checksum}  ${manifest_relative_path}" \
    && "$manifest_checksum" =~ ^[a-f0-9]{64}$ \
    && "$manifest_relative_path" =~ ^\./[A-Za-z0-9._/-]+$ ]]
}

mismatch_relative_path=''
mismatch_resume=false
parse_mismatch_record() {
  local record="$1"
  mismatch_resume=false
  if [[ "$record" == +* ]]; then
    mismatch_resume=true
    record="${record:1}"
  fi

  [[ "$record" =~ ^\./[A-Za-z0-9._/-]+$ ]] || return 1
  mismatch_relative_path="$record"
}

write_mismatch_paths() {
  local manifest_path="$1"
  local source_directory="$2"
  [[ -n "${FTP_MISMATCH_FILE:-}" ]] || return 0
  : > "$FTP_MISMATCH_FILE"

  local record local_path source_path actual_checksum actual_size source_size mismatch_record
  while IFS= read -r record; do
    parse_manifest_record "$record" || continue
    local_path="${verification_dir}/${manifest_relative_path#./}"
    source_path="${source_directory}/${manifest_relative_path#./}"
    actual_checksum=''
    if [[ -f "$local_path" ]]; then
      actual_checksum="$(sha256sum "$local_path" | cut -d ' ' -f 1)"
    fi
    if [[ "$actual_checksum" != "$manifest_checksum" ]]; then
      mismatch_record="$manifest_relative_path"
      if [[ -f "$local_path" && -f "$source_path" ]]; then
        actual_size="$(stat --format='%s' "$local_path")"
        source_size="$(stat --format='%s' "$source_path")"
        if (( actual_size > 0 && actual_size < source_size )) \
          && cmp --silent --bytes="$actual_size" "$local_path" "$source_path"; then
          # A leading + means the downloaded remote file is a verified prefix
          # and can safely be resumed instead of being truncated yet again.
          mismatch_record="+${manifest_relative_path}"
        fi
      fi
      printf '%s\n' "$mismatch_record" >> "$FTP_MISMATCH_FILE"
    fi
  done < "$manifest_path"
}

load_manifest() {
  local manifest_path="$1"
  local target_name="$2"
  local -n target_entries="$target_name"
  target_entries=()
  [[ -s "$manifest_path" ]] || return 1

  local record
  while IFS= read -r record; do
    if ! parse_manifest_record "$record"; then
      return 1
    fi
    target_entries["$manifest_relative_path"]="$manifest_checksum"
  done < "$manifest_path"

  (( ${#target_entries[@]} > 0 ))
}

fetch_remote_deployment_manifest() {
  local destination="$1"
  local commands="cd $(lftp_quote "$remote_dir"); get $(lftp_quote 'DEPLOYMENT_MANIFEST.sha256') -o $(lftp_quote "$destination");"

  lftp -c "${connection_settings} ${open_command} ${commands}" > "${transfer_dir}/manifest-download.log" 2>&1
}

upload_changed_deployment_files() {
  local local_manifest="${deploy_artifact_dir}/DEPLOYMENT_MANIFEST.sha256"
  local remote_manifest="${transfer_dir}/remote-manifest.sha256"
  declare -A local_entries=()
  declare -A remote_entries=()

  if ! load_manifest "$local_manifest" local_entries; then
    echo 'The local deployment manifest is invalid.' >&2
    exit 1
  fi
  if ! load_manifest "$remote_manifest" remote_entries; then
    echo 'The remote deployment manifest is invalid; a full artifact upload is required.'
    upload_full_deployment_artifact
    return
  fi

  if [[ "${remote_entries[./DEPLOYMENT_TRANSFER_PROTOCOL]:-}" != "${local_entries[./DEPLOYMENT_TRANSFER_PROTOCOL]:-}" ]]; then
    echo 'The remote deployment manifest predates the incremental-transfer protocol; transferring the complete artifact once.'
    upload_full_deployment_artifact
    return
  fi

  local commands="mkdir -pf $(lftp_quote "$remote_dir");"
  local relative_path remote_path local_path parent_path
  local changed=0 removed=0
  for relative_path in "${!local_entries[@]}"; do
    if [[ "${remote_entries[$relative_path]:-}" == "${local_entries[$relative_path]}" ]]; then
      continue
    fi

    remote_path="${remote_dir%/}/${relative_path#./}"
    local_path="${deploy_artifact_dir}/${relative_path#./}"
    parent_path="$(dirname "$remote_path")"
    [[ -f "$local_path" ]] || { echo "Manifest file is missing from the artifact: ${local_path}" >&2; exit 1; }
    commands+=" mkdir -pf $(lftp_quote "$parent_path"); put $(lftp_quote "$local_path") -o $(lftp_quote "$remote_path");"
    ((changed += 1))
  done

  for relative_path in "${!remote_entries[@]}"; do
    [[ -n "${local_entries[$relative_path]:-}" ]] && continue
    remote_path="${remote_dir%/}/${relative_path#./}"
    commands+=" rm -f $(lftp_quote "$remote_path");"
    ((removed += 1))
  done

  commands+=" put $(lftp_quote "$local_manifest") -o $(lftp_quote "${remote_dir%/}/DEPLOYMENT_MANIFEST.sha256");"
  echo "FTPS upload: ${changed} changed and ${removed} removed artifact files; uploading the manifest last."
  if ! lftp -c "${connection_settings} ${open_command} ${commands}"; then
    echo 'FTPS incremental artifact upload failed; the previous manifest remains authoritative for the next retry.' >&2
    exit 1
  fi
}

upload_deployment_artifact() {
  local local_manifest="${deploy_artifact_dir}/DEPLOYMENT_MANIFEST.sha256"
  [[ -s "$local_manifest" ]] || { echo "Deployment manifest is unavailable: ${local_manifest}" >&2; exit 2; }

  transfer_dir="$(mktemp -d)"
  if ! fetch_remote_deployment_manifest "${transfer_dir}/remote-manifest.sha256"; then
    upload_full_deployment_artifact
    return
  fi

  upload_changed_deployment_files
}

repair_mismatched_deployment_files() {
  local source_directory="${3:?repair source directory required}"
  local mismatch_file="${4:?repair mismatch file required}"
  source_directory="$(cd "$source_directory" && pwd)" \
    || { echo "Repair source directory is unavailable: ${source_directory}" >&2; exit 2; }
  [[ -s "$mismatch_file" ]] || { echo 'The targeted FTP repair list is missing or empty.' >&2; exit 2; }

  local local_manifest="${source_directory}/DEPLOYMENT_MANIFEST.sha256"
  declare -A local_entries=()
  if ! load_manifest "$local_manifest" local_entries; then
    echo 'The local deployment manifest is invalid.' >&2
    exit 1
  fi

  local commands="mkdir -pf $(lftp_quote "$remote_dir");"
  local mismatch_record relative_path local_path remote_path parent_path
  local repaired=0 resumed=0 repair_manifest=false
  while IFS= read -r mismatch_record; do
    if ! parse_mismatch_record "$mismatch_record"; then
      echo "The targeted FTP repair list contains an unsafe path: ${mismatch_record}" >&2
      exit 2
    fi
    relative_path="$mismatch_relative_path"
    if [[ "$relative_path" == './DEPLOYMENT_MANIFEST.sha256' ]]; then
      repair_manifest=true
      continue
    fi
    if [[ ! "$relative_path" =~ ^\./[A-Za-z0-9._/-]+$ || -z "${local_entries[$relative_path]:-}" ]]; then
      echo "The targeted FTP repair list contains an unsafe or unknown path: ${relative_path}" >&2
      exit 2
    fi

    local_path="${source_directory}/${relative_path#./}"
    remote_path="${remote_dir%/}/${relative_path#./}"
    parent_path="$(dirname "$remote_path")"
    [[ -f "$local_path" ]] || { echo "Repair source file is missing: ${local_path}" >&2; exit 1; }
    commands+=" mkdir -pf $(lftp_quote "$parent_path");"
    if [[ "$mismatch_resume" == true ]]; then
      commands+=" set xfer:use-temp-file false; cache flush;"
      commands+=" put -c $(lftp_quote "$local_path") -o $(lftp_quote "$remote_path");"
      commands+=" set xfer:use-temp-file true;"
      ((resumed += 1))
    else
      commands+=" put $(lftp_quote "$local_path") -o $(lftp_quote "$remote_path");"
    fi
    ((repaired += 1))
  done < "$mismatch_file"

  if (( repaired == 0 )) && [[ "$repair_manifest" != true ]]; then
    echo 'The targeted FTP repair list contains no artifact files.' >&2
    exit 2
  fi
  commands+=" put $(lftp_quote "$local_manifest") -o $(lftp_quote "${remote_dir%/}/DEPLOYMENT_MANIFEST.sha256");"
  echo "FTPS repair: retransferring ${repaired} mismatched artifact files " \
    "(${resumed} safely resumed from a verified remote prefix) and the manifest."
  if ! lftp -c "${connection_settings} ${open_command} ${commands}"; then
    echo 'FTPS targeted artifact repair failed.' >&2
    exit 1
  fi
}

create_verification_manifest() {
  local source_directory="$1"

  if [[ ! -d "$source_directory" ]]; then
    echo "Verification source directory is unavailable: ${source_directory}" >&2
    exit 2
  fi

  if [[ -f "$source_directory/DEPLOYMENT_MANIFEST.sha256" ]]; then
    printf '%s' "$source_directory/DEPLOYMENT_MANIFEST.sha256"
    return
  fi

  local manifest_path="${verification_dir}/local-manifest.sha256"
  (
    cd "$source_directory"
    find . -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum > "$manifest_path"
  )
  printf '%s' "$manifest_path"
}

verify_remote_files() {
  local source_directory="${3:?verification source directory required}"
  source_directory="$(cd "$source_directory" && pwd)" \
    || { echo "Verification source directory is unavailable: ${source_directory}" >&2; exit 2; }
  verification_dir="$(mktemp -d)"
  local manifest_path
  manifest_path="$(create_verification_manifest "$source_directory")"
  [[ -s "$manifest_path" ]] || { echo 'Verification manifest is missing or empty.' >&2; exit 1; }

  local verification_manifest_path="$manifest_path"
  local targeted=false
  local targeted_count=0
  local targeted_manifest=false
  local mismatch_file="${FTP_MISMATCH_FILE:-}"
  if [[ "$manifest_path" == "$source_directory/DEPLOYMENT_MANIFEST.sha256" \
    && -n "$mismatch_file" && -s "$mismatch_file" ]]; then
    declare -A expected_entries=()
    if ! load_manifest "$manifest_path" expected_entries; then
      echo 'The local deployment manifest is invalid.' >&2
      exit 1
    fi

    verification_manifest_path="${verification_dir}/target-manifest.sha256"
    : > "$verification_manifest_path"
    local requested_record requested_path
    while IFS= read -r requested_record; do
      if ! parse_mismatch_record "$requested_record"; then
        echo "The targeted FTPS verification list contains an unsafe path: ${requested_record}" >&2
        exit 2
      fi
      requested_path="$mismatch_relative_path"
      if [[ "$requested_path" == './DEPLOYMENT_MANIFEST.sha256' ]]; then
        targeted_manifest=true
        continue
      fi
      if [[ ! "$requested_path" =~ ^\./[A-Za-z0-9._/-]+$ \
        || -z "${expected_entries[$requested_path]:-}" ]]; then
        echo "The targeted FTPS verification list contains an unsafe or unknown path: ${requested_path}" >&2
        exit 2
      fi
      printf '%s  %s\n' "${expected_entries[$requested_path]}" "$requested_path" \
        >> "$verification_manifest_path"
      ((targeted_count += 1))
    done < "$mismatch_file"

    if (( targeted_count == 0 )) && [[ "$targeted_manifest" != true ]]; then
      echo 'The targeted FTPS verification list contains no artifact files.' >&2
      exit 2
    fi
    targeted=true
  fi

  local download_commands="cd $(lftp_quote "$remote_dir");"
  local remote_manifest_path="${verification_dir}/remote-manifest.sha256"
  if [[ "$manifest_path" == "$source_directory/DEPLOYMENT_MANIFEST.sha256" ]]; then
    download_commands+=" get $(lftp_quote 'DEPLOYMENT_MANIFEST.sha256') -o $(lftp_quote "$remote_manifest_path");"
  fi

  local record checksum relative_path remote_path destination
  while IFS= read -r record; do
    checksum="${record:0:64}"
    relative_path="${record:66}"
    if [[ ! "$checksum" =~ ^[a-f0-9]{64}$ || ! "$relative_path" =~ ^\./[A-Za-z0-9._/-]+$ ]]; then
      echo "Verification manifest contains an unsafe entry: ${record}" >&2
      exit 2
    fi

    remote_path="${relative_path#./}"
    destination="${verification_dir}/${remote_path}"
    mkdir -p "$(dirname "$destination")"
    download_commands+=" get $(lftp_quote "$remote_path") -o $(lftp_quote "$destination");"
  done < "$verification_manifest_path"

  if [[ "$targeted" == true ]]; then
    echo "FTPS targeted verification: downloading ${targeted_count} repaired artifact files from ${remote_dir}."
  else
    echo "FTPS verification: downloading the expected files from ${remote_dir}."
  fi
  if ! lftp -c "${connection_settings} ${open_command} ${download_commands}"; then
    echo 'FTPS verification download failed; the remote artifact is incomplete or unreadable.' >&2
    exit 1
  fi

  local manifest_failed=false
  if [[ -f "$remote_manifest_path" ]] && ! cmp --silent "$manifest_path" "$remote_manifest_path"; then
    manifest_failed=true
  fi

  local checksum_failed=false
  if [[ -s "$verification_manifest_path" ]] \
    && ! (cd "$verification_dir" && sha256sum --check --status --strict "$verification_manifest_path"); then
    checksum_failed=true
    write_mismatch_paths "$verification_manifest_path" "$source_directory"
  elif [[ -n "$mismatch_file" ]]; then
    : > "$mismatch_file"
  fi

  if [[ "$manifest_failed" == true ]]; then
    if [[ -n "$mismatch_file" ]]; then
      printf '%s\n' './DEPLOYMENT_MANIFEST.sha256' >> "$mismatch_file"
    fi
    echo 'Remote deployment manifest does not match the tested artifact.' >&2
  fi

  if [[ "$checksum_failed" == true ]]; then
    echo 'Remote file checksum verification failed.' >&2
    (cd "$verification_dir" && sha256sum --check --strict "$verification_manifest_path") >&2 || true
  fi

  if [[ "$manifest_failed" == true || "$checksum_failed" == true ]]; then
    exit 1
  fi

  if [[ "$targeted" == true ]]; then
    echo "FTPS targeted verification succeeded for ${remote_dir}."
  else
    echo "FTPS verification succeeded for ${remote_dir}."
  fi
}

if ! lftp -c "${connection_settings} ${open_command} quote PWD;"; then
  echo "FTPS connection, certificate, or authentication failed for ${FTP_SERVER}:${FTP_PORT}." >&2
  exit 1
fi

if [[ "$operation" == 'verify' ]]; then
  verify_remote_files "$@"
  exit 0
fi

if [[ "$operation" == 'deploy' ]]; then
  upload_deployment_artifact
  exit 0
fi

if [[ "$operation" == 'deploy-full' ]]; then
  [[ -s "${deploy_artifact_dir}/DEPLOYMENT_MANIFEST.sha256" ]] \
    || { echo "Deployment manifest is unavailable: ${deploy_artifact_dir}/DEPLOYMENT_MANIFEST.sha256" >&2; exit 2; }
  upload_full_deployment_artifact
  exit 0
fi

if [[ "$operation" == 'repair' ]]; then
  repair_mismatched_deployment_files "$@"
  exit 0
fi

commands="$(upload_commands)" || { echo 'Unknown upload operation' >&2; exit 2; }
echo "FTPS preflight succeeded; starting ${operation} upload."
if ! lftp -c "${connection_settings} ${open_command} ${commands}"; then
  echo "FTPS upload failed after a successful preflight; inspect remote directory permissions and available quota." >&2
  exit 1
fi
