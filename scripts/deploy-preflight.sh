#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Keep the non-secret deployment contract executable both before a push and in
# GitHub Actions. The upload, migration, and remote health checks intentionally
# remain in the deploy workflow because they need environment credentials and a
# running hosting target.
bash "${ROOT_DIR}/scripts/build-deploy.sh"
bash "${ROOT_DIR}/scripts/test-deploy-artifact.sh" "${ROOT_DIR}/build/deploy"
bash "${ROOT_DIR}/scripts/stage-root-router.sh" "${ROOT_DIR}/build/root-router"

echo "Deployment preflight verified the artifact and root router."
