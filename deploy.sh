#!/usr/bin/env bash
# deploy.sh — upgrade one or all Helm releases using tags from versions.yaml
#
# Usage:
#   ./deploy.sh                  # upgrade everything
#   ./deploy.sh control-plane    # upgrade one service
#   ./deploy.sh tenant-cfd4486   # upgrade one tenant
#
# Run on the bastion inside ~/gitops after git pull.

set -euo pipefail

VERSIONS_FILE="$(dirname "$0")/versions.yaml"

# read a tag from versions.yaml (requires yq)
tag() {
  yq e ".[\"$1\"]" "$VERSIONS_FILE"
}

deploy_control_plane() {
  echo "==> control-plane  tag=$(tag control-plane)"
  helm upgrade linexa-dev ./helm/control-plane \
    -n linexa-dev \
    -f helm/control-plane/values.yaml \
    -f helm/control-plane/values-dev.yaml \
    --set image.tag="$(tag control-plane)"
  kubectl rollout status deployment/linexa-dev -n linexa-dev
}

deploy_tenant() {
  local id="$1"
  local ns="linexa-tenant-${id}"
  echo "==> tenant-${id}  tag=$(tag "tenant-${id}")"
  helm upgrade --install "tenant-${id}" ./helm/tenant \
    -n "$ns" \
    -f "./helm/tenant-${id}.yaml" \
    --set image.tag="$(tag "tenant-${id}")"
  kubectl rollout status "deployment/tenant-${id}" -n "$ns"
}

TARGET="${1:-all}"

case "$TARGET" in
  control-plane)
    deploy_control_plane
    ;;
  tenant-*)
    deploy_tenant "${TARGET#tenant-}"
    ;;
  all)
    deploy_control_plane
    # deploy every tenant listed in versions.yaml
    for key in $(yq e 'keys | .[]' "$VERSIONS_FILE" | grep '^tenant-'); do
      deploy_tenant "${key#tenant-}"
    done
    ;;
  *)
    echo "Unknown target: $TARGET"
    echo "Usage: $0 [control-plane | tenant-<ID> | all]"
    exit 1
    ;;
esac

echo ""
echo "Done. Running pods:"
kubectl get pods -A | grep linexa-
