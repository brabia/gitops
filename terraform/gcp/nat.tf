# ── Cloud NAT — lets private nodes reach the internet for image pulls ─────────
# Step: egress gateway for the private subnet.
# GKE nodes have no public IP (enable_private_nodes = true), so they need NAT
# to pull images from GAR and reach apt/package mirrors.
# Cloud Router advertises the subnet's routes; Cloud NAT assigns ephemeral
# external IPs automatically (NAT_IP_ALLOCATE_OPTION = AUTO_ONLY).

# Cloud Router — needed as the attachment point for Cloud NAT
resource "google_compute_router" "linexa" {
  name    = "${var.cluster_name}-router"
  region  = var.region
  network = google_compute_network.linexa.id

  depends_on = [google_project_service.compute]
}

# Cloud NAT gateway — translates outbound packets from private nodes to a
# temporary public IP; inbound connections from the internet are still blocked
resource "google_compute_router_nat" "linexa" {
  name                               = "${var.cluster_name}-nat"
  router                             = google_compute_router.linexa.name
  region                             = var.region
  nat_ip_allocate_option             = "AUTO_ONLY"
  source_subnetwork_ip_ranges_to_nat = "ALL_SUBNETWORKS_ALL_IP_RANGES"
}
