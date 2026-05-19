#!/bin/bash

set -e

STATE_FILE="$HOME/.scada_env"

if [ ! -f "$STATE_FILE" ]; then
    echo "No deployment state found."
    exit 1
fi

ENV=$(cat "$STATE_FILE")

echo "Cleaning environment: $ENV"

case "$ENV" in
    default)
        helm uninstall scada -n scada-system || true
        ;;

    dev)
        helm uninstall scada-dev -n scada-dev || true
        ;;

    prod)
        helm uninstall scada-prod -n scada-prod || true
        ;;
esac

minikube stop || true
minikube delete || true

rm -f "$STATE_FILE"

echo "Cleanup completed."