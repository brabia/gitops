# ── GKE cluster ───────────────────────────────────────────────────────────────
resource "google_container_cluster" "linexa" {
  name     = var.cluster_name
  location = var.zone

  # Remove the default node pool — we manage our own below
  remove_default_node_pool = true
  initial_node_count       = 1

  network    = google_compute_network.linexa.name
  subnetwork = google_compute_subnetwork.linexa.name

  # VPC-native (alias IPs) — required for proper K8s networking on GKE
  ip_allocation_policy {
    cluster_secondary_range_name  = "pods"
    services_secondary_range_name = "services"
  }

  # Workload Identity — lets K8s service accounts act as GCP service accounts
  workload_identity_config {
    workload_pool = "${var.project_id}.svc.id.goog"
  }

  deletion_protection = false

  # Fully private cluster — no public endpoint; access via IAP bastion
  private_cluster_config {
    enable_private_nodes    = true
    enable_private_endpoint = true           # master only reachable from inside VPC
    master_ipv4_cidr_block  = "172.16.0.0/28"
  }

  # Allow the bastion subnet to reach the private master endpoint
  master_authorized_networks_config {
    cidr_blocks {
      cidr_block   = "10.0.0.0/20"
      display_name = "bastion-subnet"
    }
  }

  master_auth {
    client_certificate_config {
      issue_client_certificate = false       # use OIDC / gcloud auth instead
    }
  }

  # Enable Calico NetworkPolicy enforcement
  network_policy {
    enabled  = true
    provider = "CALICO"
  }

  # Calico requires the HTTP LB add-on enabled
  addons_config {
    http_load_balancing {
      disabled = false
    }
    network_policy_config {
      disabled = false
    }
  }

  depends_on = [
    google_project_service.container,
    google_project_service.compute,
  ]
}

# ── Node pool ─────────────────────────────────────────────────────────────────
resource "google_container_node_pool" "linexa" {
  name       = "${var.cluster_name}-nodes"
  cluster    = google_container_cluster.linexa.name
  location   = var.zone
  node_count = var.node_count

  management {
    auto_repair  = true
    auto_upgrade = true
  }

  node_config {
    machine_type = var.machine_type
    disk_size_gb = 50
    disk_type    = "pd-ssd"

    # Use Container-Optimized OS with containerd (no Docker daemon)
    image_type = "COS_CONTAINERD"

    # Workload Identity on the node pool
    workload_metadata_config {
      mode = "GKE_METADATA"
    }

    oauth_scopes = [
      "https://www.googleapis.com/auth/cloud-platform",
    ]

    shielded_instance_config {
      enable_secure_boot          = true
      enable_integrity_monitoring = true
    }
  }
}
