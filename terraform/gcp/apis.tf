# ── Enable required GCP APIs ──────────────────────────────────────────────────
# Step: API activation — must succeed before any other resource can be created.
# GCP resources fail silently if the API is not enabled; enabling idempotently
# here means `terraform apply` handles it automatically.

# container.googleapis.com — GKE (Kubernetes Engine) service
resource "google_project_service" "container" {
  service            = "container.googleapis.com"
  disable_on_destroy = false
}

# compute.googleapis.com — Compute Engine (VMs, VPCs, Cloud NAT, Armor, LB)
resource "google_project_service" "compute" {
  service            = "compute.googleapis.com"
  disable_on_destroy = false
}

# iap.googleapis.com — Identity-Aware Proxy, tunnels SSH to the bastion
# without exposing port 22 on a public IP
resource "google_project_service" "iap" {
  service            = "iap.googleapis.com"
  disable_on_destroy = false
}
