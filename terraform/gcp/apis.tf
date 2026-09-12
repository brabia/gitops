# ── Enable required GCP APIs ──────────────────────────────────────────────────
resource "google_project_service" "container" {
  service            = "container.googleapis.com"
  disable_on_destroy = false
}

resource "google_project_service" "compute" {
  service            = "compute.googleapis.com"
  disable_on_destroy = false
}

# IAP — Identity-Aware Proxy, the internet-facing layer for the bastion tunnel
resource "google_project_service" "iap" {
  service            = "iap.googleapis.com"
  disable_on_destroy = false
}
