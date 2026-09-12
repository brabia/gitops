# ── Cloud NAT — lets private nodes reach the internet for image pulls ─────────
resource "google_compute_router" "linexa" {
  name    = "${var.cluster_name}-router"
  region  = var.region
  network = google_compute_network.linexa.id

  depends_on = [google_project_service.compute]
}

resource "google_compute_router_nat" "linexa" {
  name                               = "${var.cluster_name}-nat"
  router                             = google_compute_router.linexa.name
  region                             = var.region
  nat_ip_allocate_option             = "AUTO_ONLY"
  source_subnetwork_ip_ranges_to_nat = "ALL_SUBNETWORKS_ALL_IP_RANGES"
}
