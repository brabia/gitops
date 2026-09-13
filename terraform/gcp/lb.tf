# ── Static global IP for the GCP HTTP(S) Load Balancer ────────────────────────
# Step: reserve a stable external IP for the Ingress LB.
# GKE Ingress (class: gce) creates an HTTP(S) LB automatically; by pre-reserving
# this named IP we keep the same address across helm uninstall / reinstall cycles.
# The actual LB and forwarding rules are created by GKE when the Ingress resource
# is applied — Terraform only reserves the IP here.
#
# Reference this name in helm values: ingress.staticIpName = "linexa-dev-ip"

resource "google_compute_global_address" "linexa_dev" {
  name    = "linexa-dev-ip"
  project = var.project_id

  depends_on = [google_project_service.compute]
}
