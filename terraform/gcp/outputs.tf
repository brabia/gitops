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

output "get_credentials_command" {
  description = "Run this after apply to configure kubectl"
  value       = "gcloud container clusters get-credentials ${var.cluster_name} --zone ${var.zone} --project ${var.project_id}"
}

output "dev_lb_ip" {
  description = "Static IP for the dev LB â€” point dev.linexa.eu (and tenant subdomains) here"
  value       = google_compute_global_address.linexa_dev.address
}

