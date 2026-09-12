# ── VPC + Subnet ──────────────────────────────────────────────────────────────
resource "google_compute_network" "linexa" {
  name                    = "${var.cluster_name}-vpc"
  auto_create_subnetworks = false

  depends_on = [
    google_project_service.compute,
    google_project_service.container,
  ]
}

resource "google_compute_subnetwork" "linexa" {
  name          = "${var.cluster_name}-subnet"
  network       = google_compute_network.linexa.id
  region        = var.region
  ip_cidr_range = "10.0.0.0/20"

  secondary_ip_range {
    range_name    = "pods"
    ip_cidr_range = "10.48.0.0/14"
  }
  secondary_ip_range {
    range_name    = "services"
    ip_cidr_range = "10.52.0.0/20"
  }
}
