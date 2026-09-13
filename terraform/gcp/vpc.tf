# ── VPC + Subnet ──────────────────────────────────────────────────────────────
# Step: networking foundation — everything else depends on this.
# Custom-mode VPC (auto_create_subnetworks = false) gives us full control over
# CIDRs.  The subnet carries two secondary ranges that GKE uses for pod and
# service IPs (alias-IP / VPC-native mode).
resource "google_compute_network" "linexa" {
  name                    = "${var.cluster_name}-vpc"
  auto_create_subnetworks = false

  depends_on = [
    google_project_service.compute,
    google_project_service.container,
  ]
}

# Subnet: primary CIDR 10.0.0.0/20 (nodes + bastion).
# Secondary ranges are required for VPC-native GKE — GKE refuses to create
# without them when ip_allocation_policy references named ranges.
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
