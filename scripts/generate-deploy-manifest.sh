#!/usr/bin/env bash
set -euo pipefail

if (( $# != 1 )); then
  echo "Usage: bash scripts/generate-deploy-manifest.sh <artifact-directory>" >&2
  exit 2
fi

artifact_dir="$1"
manifest_name='DEPLOYMENT_MANIFEST.sha256'
manifest_path="${artifact_dir}/${manifest_name}"

if [[ ! -d "$artifact_dir" ]]; then
  echo "Artifact directory is unavailable: ${artifact_dir}" >&2
  exit 1
fi

(
  cd "$artifact_dir"
  find . -type f ! -name "$manifest_name" -print0 \
    | LC_ALL=C sort -z \
    | xargs -0 sha256sum > "$manifest_name"
  sha256sum --check --status --strict "$manifest_name"
)

if [[ ! -s "$manifest_path" ]]; then
  echo "Deployment manifest is missing or empty: ${manifest_path}" >&2
  exit 1
fi

echo "Deployment manifest generated: ${manifest_path}"
