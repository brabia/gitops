# ── Static global IP for the GCP HTTP(S) Load Balancer ────────────────────────
# Reserving a static IP prevents the LB address from changing when the Ingress
# is deleted and recreated (e.g. during a helm uninstall / reinstall).
# Reference this name in helm values: ingress.staticIpName = "linexa-dev-ip"

resource "google_compute_global_address" "linexa_dev" {
  name    = "linexa-dev-ip"
  project = var.project_id

  depends_on = [google_project_service.compute]
}
