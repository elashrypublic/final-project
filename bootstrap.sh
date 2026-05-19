#!/bin/bash

set -e

# =========================
# Colors
# =========================
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

PROJECT_DIR="$HOME/final-project"
REPO_URL="https://github.com/elashrypublic/final-project.git"
STATE_FILE="$HOME/.scada_env"

log() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# =========================
# Update System
# =========================
log "Updating system..."
sudo apt update && sudo apt upgrade -y

# =========================
# Docker
# =========================
install_docker() {
    log "Installing Docker..."
    sudo apt install -y docker.io
    sudo systemctl enable docker
    sudo systemctl start docker
    sudo usermod -aG docker "$USER"
}

if command -v docker >/dev/null 2>&1; then
    log "Docker already installed."
else
    install_docker
fi

# =========================
# kubectl
# =========================
if command -v kubectl >/dev/null 2>&1; then
    log "kubectl already installed."
else
    log "Installing kubectl..."
    sudo snap install kubectl --classic
fi

# =========================
# Minikube
# =========================
if command -v minikube >/dev/null 2>&1; then
    log "Minikube already installed."
else
    log "Installing Minikube..."
    curl -LO https://storage.googleapis.com/minikube/releases/latest/minikube-linux-amd64
    sudo install minikube-linux-amd64 /usr/local/bin/minikube
    rm -f minikube-linux-amd64
fi

# =========================
# Helm
# =========================
if command -v helm >/dev/null 2>&1; then
    log "Helm already installed."
else
    log "Installing Helm..."
    curl https://raw.githubusercontent.com/helm/helm/main/scripts/get-helm-3 | bash
fi

# =========================
# Start Minikube
# =========================
log "Starting Minikube..."
minikube start

# =========================
# Enable Addons
# =========================
log "Enabling addons..."
minikube addons enable ingress
minikube addons enable metrics-server

# =========================
# Wait for cluster
# =========================
log "Waiting for cluster readiness..."
kubectl wait --for=condition=Ready nodes --all --timeout=300s

log "Waiting for ingress controller..."
kubectl wait \
  --namespace ingress-nginx \
  --for=condition=ready pod \
  --selector=app.kubernetes.io/component=controller \
  --timeout=300s

# =========================
# Clone / Pull Project
# =========================
if [ -d "$PROJECT_DIR" ]; then
    log "Project exists. Pulling latest changes..."
    cd "$PROJECT_DIR"
    git pull
else
    log "Cloning project..."
    git clone "$REPO_URL" "$PROJECT_DIR"
    cd "$PROJECT_DIR"
fi

# =========================
# Environment Selection
# =========================
echo
read -p "Choose environment [default/dev/prod]: " ENV

ENV=$(echo "$ENV" | tr '[:upper:]' '[:lower:]')

if [[ "$ENV" == "" ]]; then
    ENV="default"
elif [[ "$ENV" == d* ]]; then
    ENV="dev"
elif [[ "$ENV" == p* ]]; then
    ENV="prod"
else
    ENV="default"
fi

echo "$ENV" > "$STATE_FILE"

log "Selected environment: $ENV"

# =========================
# Deploy
# =========================
case "$ENV" in
    default)
        helm upgrade --install scada ./scada-helm \
            --namespace scada-system \
            --create-namespace

        HOSTNAME="scada.local"
        ;;

    dev)
        helm upgrade --install scada-dev ./scada-helm \
            -f scada-helm/values-dev.yaml \
            --namespace scada-dev \
            --create-namespace

        HOSTNAME="scada-dev.local"
        ;;

    prod)
        helm upgrade --install scada-prod ./scada-helm \
            -f scada-helm/values-prod.yaml \
            --namespace scada-prod \
            --create-namespace

        HOSTNAME="scada.local"
        ;;
esac

# =========================
# Hosts
# =========================
MINIKUBE_IP=$(minikube ip)

if ! grep -q "$HOSTNAME" /etc/hosts; then
    echo "$MINIKUBE_IP $HOSTNAME" | sudo tee -a /etc/hosts > /dev/null
    log "Added host entry: $MINIKUBE_IP $HOSTNAME"
else
    warn "$HOSTNAME already exists in /etc/hosts"
fi

# =========================
# Status
# =========================
log "Deployment complete."

kubectl get pods -A
kubectl get svc -A
kubectl get ingress -A

echo
log "Access via: http://$HOSTNAME"