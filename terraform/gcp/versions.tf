# ── Provider & backend — run first, no resources created here ─────────────────
# This block pins the Terraform and Google provider versions and optionally
# configures a GCS backend for shared state.  Run `terraform init` after any
# change to this file.
terraform {
  required_version = ">= 1.6"

  required_providers {
    google = {
      source  = "hashicorp/google"
      version = "~> 5.0"
    }
  }

  # Uncomment to store state in GCS (recommended for teams)
  # backend "gcs" {
  #   bucket = "YOUR_PROJECT_ID-tfstate"
  #   prefix = "linexa/gke"
  # }
}

provider "google" {
  project = var.project_id
  region  = var.region
}
