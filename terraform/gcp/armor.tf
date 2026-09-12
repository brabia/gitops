# ── Cloud Armor — IP allowlist for the dev environment ────────────────────────
# Restricts the GCP HTTP(S) Load Balancer to approved IPs only.
# All other traffic receives a 403 Forbidden response.
# Add IPs to var.dev_allowed_ips in variables.tf.

resource "google_compute_security_policy" "dev_allowlist" {
  name    = "${var.cluster_name}-dev-allowlist"
  project = var.project_id

  # ── Allow approved IPs ─────────────────────────────────────────────────────
  rule {
    action      = "allow"
    priority    = 1000
    description = "Allow approved IPs"
    match {
      versioned_expr = "SRC_IPS_V1"
      config {
        src_ip_ranges = var.dev_allowed_ips
      }
    }
  }

  # ── Deny everything else ───────────────────────────────────────────────────
  rule {
    action      = "deny(403)"
    priority    = 2147483647
    description = "Deny all other traffic"
    match {
      versioned_expr = "SRC_IPS_V1"
      config {
        src_ip_ranges = ["*"]
      }
    }
  }

  depends_on = [google_project_service.compute]
}
