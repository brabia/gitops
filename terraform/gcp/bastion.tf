# ── Bastion host — accessed via IAP tunnel, no public IP ──────────────────────

# Dedicated service account — minimal permissions
resource "google_service_account" "bastion" {
  account_id   = "${var.cluster_name}-bastion"
  display_name = "Bastion host"
}

# Bastion needs to fetch GKE credentials and run kubectl
resource "google_project_iam_member" "bastion_gke" {
  project = var.project_id
  role    = "roles/container.developer"
  member  = "serviceAccount:${google_service_account.bastion.email}"
}

# The VM itself — e2-micro (2 vCPU shared / 1 GB), cheapest option ~$6/month
resource "google_compute_instance" "bastion" {
  name         = "${var.cluster_name}-bastion"
  machine_type = "e2-micro"
  zone         = var.zone
  tags         = ["bastion"]

  boot_disk {
    initialize_params {
      image = "debian-cloud/debian-12"
      size  = 10
      type  = "pd-standard"
    }
  }

  network_interface {
    network    = google_compute_network.linexa.id
    subnetwork = google_compute_subnetwork.linexa.id
    # No access_config block = no public IP assigned
  }

  metadata = {
    # OS Login: SSH auth via Google account — no SSH key management
    enable-oslogin = "TRUE"

    # Runs once on first boot — pre-installs every tool needed to manage the cluster
    startup-script = <<-EOF
      #!/bin/bash
      # Redirect all output to log from the start (visible even on failure)
      exec > /var/log/bastion-bootstrap.log 2>&1
      set -euxo pipefail

      apt-get update -qq

      # git — to clone the repo
      apt-get install -y git

      # kubectl + GKE auth plugin (both from the pre-configured Google Cloud apt repo)
      apt-get install -y kubectl google-cloud-cli-gke-gcloud-auth-plugin

      # helm — official binary via get.helm.sh (avoids baltocdn.com SSL issues)
      HELM_VERSION=3.16.4
      curl -fsSL "https://get.helm.sh/helm-v$${HELM_VERSION}-linux-amd64.tar.gz" \
        | tar xz -C /tmp
      install -m 0755 /tmp/linux-amd64/helm /usr/local/bin/helm
      rm -rf /tmp/linux-amd64

      echo "bastion bootstrap complete"
    EOF
  }

  service_account {
    email  = google_service_account.bastion.email
    scopes = ["cloud-platform"]
  }

  shielded_instance_config {
    enable_secure_boot          = true
    enable_integrity_monitoring = true
  }

  depends_on = [
    google_project_service.compute,
    google_project_service.iap,
  ]
}

# Firewall: ONLY allow IAP's IP range to SSH into the bastion
# 35.235.240.0/20 is Google's fixed IAP tunnel source range — not the open internet
resource "google_compute_firewall" "iap_ssh" {
  name    = "${var.cluster_name}-allow-iap-ssh"
  network = google_compute_network.linexa.id

  allow {
    protocol = "tcp"
    ports    = ["22"]
  }

  source_ranges = ["35.235.240.0/20"]  # IAP only — no direct internet SSH
  target_tags   = ["bastion"]
}
