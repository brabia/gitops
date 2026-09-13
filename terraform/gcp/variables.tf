# ── Input variables ────────────────────────────────────────────────────────────
# All variables have safe defaults for the linexa dev environment.
# Override them in a terraform.tfvars file (git-ignored) or via -var flags.

variable "project_id" {
  description = "GCP project ID"
  type        = string
  default     = "replenit-lab"
}

variable "region" {
  description = "GCP region"
  type        = string
  default     = "europe-west1"
}

variable "zone" {
  description = "GCP zone for the cluster"
  type        = string
  default     = "europe-west1-b"
}

variable "cluster_name" {
  description = "GKE cluster name"
  type        = string
  default     = "linexa"
}

variable "node_count" {
  description = "Number of nodes in the default node pool"
  type        = number
  default     = 1   # dev: one e2-standard-2 fits the full stack (app + mysql)
}

variable "machine_type" {
  description = "GCE machine type for nodes"
  type        = string
  default     = "e2-small"         # 2 vCPU / 2 GB — sufficient for php-fpm + nginx
}

variable "dev_allowed_ips" {
  description = "CIDR blocks allowed through Cloud Armor to the dev environment. Add your IP with /32."
  type        = list(string)
  default     = ["176.189.87.87/32"]  # update when your IP changes: curl -s ifconfig.me
}
