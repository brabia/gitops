# ── Outputs — values printed after `terraform apply` ──────────────────────────
# These are read-only; changing them doesn't affect real resources.
# Use them to configure kubectl and /etc/hosts after provisioning.

output "cluster_name" {
  description = "GKE cluster name"
  value       = google_container_cluster.linexa.name
}

output "cluster_endpoint" {
  description = "GKE cluster endpoint"
  value       = google_container_cluster.linexa.endpoint
  sensitive   = true
}

output "region" {
  value = var.region
}

output "zone" {
  value = var.zone
}

output “get_credentials_command” {
  description = “Run this after apply to configure kubectl on the bastion”
  value       = “gcloud container clusters get-credentials ${var.cluster_name} --zone ${var.zone} --project ${var.project_id}”
}

output “dev_lb_ip” {
  description = “Static IP for the dev LB — add to /etc/hosts for dev.linexa.eu and all tenant subdomains”
  value       = google_compute_global_address.linexa_dev.address
}

