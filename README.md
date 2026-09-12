# Linexa — GitOps Demo

> PHP · Nginx · Docker · Terraform · GKE · Helm

A multi-tenant platform deployed on GKE via Helm. Infrastructure managed by Terraform, apps deployed manually (CI/CD is a next step). The GKE cluster is fully private — only accessible via an IAP bastion.

---

## Architecture

```
┌──────────────────────────────────────────────────────────────┐
│  Git Repository  (source of truth for code AND infra)        │
│  ├── app/control-plane/   PHP + nginx app + Dockerfile       │
│  ├── app/tenants/         Static tenant pages (preview)      │
│  ├── helm/                Helm charts                        │
│  └── terraform/gcp/       GCP infrastructure                 │
└──────────────────┬───────────────────────────────────────────┘
                   │  docker build + push (laptop)
                   ▼
┌──────────────────────────────────────────────────────────────┐
│  Google Artifact Registry                                    │
│  europe-west1-docker.pkg.dev/replenit-lab/linexa/            │
└──────────────────┬───────────────────────────────────────────┘
                   │  gcloud compute ssh --tunnel-through-iap
                   ▼
┌──────────────────────────────────────────────────────────────┐
│  IAP Bastion  (e2-micro, no public IP)                       │
└──────────────────┬───────────────────────────────────────────┘
                   │  private VPC → helm upgrade
                   ▼
┌──────────────────────────────────────────────────────────────┐
│  GKE Cluster  (replenit-lab / europe-west1-b)                │
│  private nodes + private master endpoint                     │
│  ├── linexa-dev          (control plane — PHP + nginx)       │
│  ├── linexa-tenant-cfd4486                                   │
│  └── linexa-tenant-3622fab                                   │
│       All behind GCP HTTP(S) LB → static IP linexa-dev-ip   │
└──────────────────────────────────────────────────────────────┘
```

---

## Phase 1 — GCP Infrastructure (laptop)

> **Prerequisites:** [gcloud CLI](https://cloud.google.com/sdk/docs/install) · [Terraform](https://developer.hashicorp.com/terraform/install) ≥ 1.6

```bash
gcloud auth application-default login
gcloud config set project replenit-lab

cd terraform/gcp
terraform init
terraform apply        # ~10 min — GKE cluster, bastion, static IP, Cloud Armor
```

| File | What it creates |
|------|----------------|
| `vpc.tf` | linexa-vpc + subnet + pod/service CIDRs |
| `nat.tf` | Cloud NAT (private nodes pull images) |
| `bastion.tf` | e2-micro bastion, IAP-only SSH, startup script (kubectl + helm) |
| `gke.tf` | Fully private GKE cluster, 1 × e2-standard-2 |
| `lb.tf` | Static global IP `linexa-dev-ip` |
| `armor.tf` | Cloud Armor IP allowlist `linexa-dev-allowlist` |

---

## Phase 2 — Build & Push the Image (laptop)

```bash
# One-time: create the registry
gcloud artifacts repositories create linexa \
  --repository-format=docker \
  --location=europe-west1 \
  --project=replenit-lab

# Authenticate
gcloud auth configure-docker europe-west1-docker.pkg.dev

# Build and push
TAG=$(git rev-parse --short HEAD)
docker build -t europe-west1-docker.pkg.dev/replenit-lab/linexa/control-plane:$TAG \
  app/control-plane/
docker push europe-west1-docker.pkg.dev/replenit-lab/linexa/control-plane:$TAG
```

---

## Phase 3 — Deploy Apps (bastion)

### SSH into the bastion

```bash
gcloud compute ssh linexa-bastion \
  --tunnel-through-iap \
  --zone europe-west1-b \
  --project replenit-lab
```

Wait for the startup script to finish (installs kubectl + helm):

```bash
sudo tail -f /var/log/bastion-bootstrap.log
```

### Connect to the cluster

```bash
gcloud container clusters get-credentials linexa \
  --zone europe-west1-b \
  --internal-ip \
  --project replenit-lab

kubectl get nodes    # 1 node Ready
```

### Clone the repo

```bash
git clone https://github.com/brabia/gitops.git
cd gitops
```

### Create namespaces and deploy

```bash
kubectl apply -f k8s/namespaces/

helm upgrade --install linexa-dev ./helm/control-plane \
  -n linexa-dev \
  -f helm/control-plane/values-dev.yaml \
  --set image.tag=<TAG>

helm upgrade --install tenant-cfd4486 ./helm/tenant \
  -n linexa-tenant-cfd4486 \
  -f helm/tenant-cfd4486.yaml

helm upgrade --install tenant-3622fab ./helm/tenant \
  -n linexa-tenant-3622fab \
  -f helm/tenant-3622fab.yaml
```

### Get the LB IP

```bash
kubectl get ingress -n linexa-dev    # wait for ADDRESS (~5 min)
```

Add to `/etc/hosts` on your laptop to test before DNS:

```
<LB-IP>  dev.linexa.eu dev.tenant-cfd4486.linexa.eu dev.tenant-3622fab.linexa.eu
```

---

## Updating After a Code Change

```bash
# Laptop — rebuild and push
TAG=$(git rev-parse --short HEAD)
docker build -t europe-west1-docker.pkg.dev/replenit-lab/linexa/control-plane:$TAG \
  app/control-plane/
docker push europe-west1-docker.pkg.dev/replenit-lab/linexa/control-plane:$TAG

# Bastion — deploy new tag
helm upgrade linexa-dev ./helm/control-plane \
  -n linexa-dev \
  -f helm/control-plane/values-dev.yaml \
  --set image.tag=$TAG
```

---

## Teardown

```bash
# Bastion — remove helm releases first
helm uninstall linexa-dev -n linexa-dev
helm uninstall tenant-cfd4486 -n linexa-tenant-cfd4486
helm uninstall tenant-3622fab -n linexa-tenant-3622fab

# Laptop — destroy all GCP resources
cd terraform/gcp && terraform destroy
```

---

## Project Structure

```
├── app/
│   ├── control-plane/
│   │   ├── Dockerfile           php:8.2-fpm-alpine + nginx in one container
│   │   ├── nginx/default.conf   nginx → FastCGI → php-fpm
│   │   └── src/
│   │       └── index.php        Control plane page (shows live date)
│   └── tenants/
│       ├── cfd4486/index.html   Static preview for tenant cfd4486
│       └── 3622fab/index.html   Static preview for tenant 3622fab
│
├── helm/
│   ├── control-plane/           Chart for the control-plane app
│   │   ├── templates/
│   │   │   ├── deployment.yaml  Pulls image from values
│   │   │   ├── service.yaml     NodePort (required by GKE GCE Ingress)
│   │   │   ├── ingress.yaml     GKE Ingress → GCP HTTP(S) LB
│   │   │   ├── backend-config.yaml  BackendConfig (Cloud Armor hook)
│   │   │   └── configmap.yaml   Optional nginx config override
│   │   ├── values.yaml          Defaults
│   │   └── values-dev.yaml      Dev overrides (NodePort, ingress, image)
│   ├── tenant/                  Shared chart for all tenants
│   │   └── templates/
│   │       ├── deployment.yaml  nginx:alpine + HTML from ConfigMap
│   │       ├── service.yaml
│   │       ├── ingress.yaml
│   │       ├── backend-config.yaml
│   │       └── configmap.yaml   Tenant hello page (tenantId injected)
│   ├── tenant-cfd4486.yaml      Values for tenant cfd4486
│   └── tenant-3622fab.yaml      Values for tenant 3622fab
│
├── k8s/
│   └── namespaces/              Namespace + ResourceQuota + LimitRange
│
├── terraform/gcp/               All GCP infrastructure
│
└── docs/
    └── overview.html            Architecture diagram
```

---

## Debugging

```bash
# Bastion
kubectl -n linexa-dev get all
kubectl -n linexa-dev logs deploy/linexa-dev -f
kubectl -n linexa-dev describe ingress linexa-dev

helm status linexa-dev -n linexa-dev
helm history linexa-dev -n linexa-dev
helm rollback linexa-dev -n linexa-dev
```

---

## Next Steps

| # | What | Why |
|---|------|-----|
| 1 | **GitHub Actions CI** | Build → push → update image tag on commit |
| 2 | **Real DNS A records** | Point `*.linexa.eu` to the static IP |
| 3 | **Enable ManagedCertificate** | Set `managedCert: true` once DNS is live |
| 4 | **Enable Cloud Armor** | Set `cloudArmor.enabled: true` after `terraform apply` |

---

## License

MIT
