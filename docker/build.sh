#!/bin/bash

set -e  # Beende bei Fehlern

# Signal-Handler für sauberes Beenden
cleanup() {
    echo ""
    echo "Script wird beendet..."
    exit 130
}

trap cleanup SIGINT SIGTERM

echo "=== Docker Build Script ==="

# Lade .env Datei (vom Root-Verzeichnis des Projekts)
# In CI/CD-Umgebungen werden die Variablen als Environment-Variablen gesetzt
if [ -f "../../.env" ]; then
    ENV_FILE="../../.env"
elif [ -f "../.env" ]; then
    ENV_FILE="../.env"
elif [ -f ".env" ]; then
    ENV_FILE=".env"
else
    echo "Keine .env Datei gefunden, verwende Environment-Variablen (für CI/CD)"
    ENV_FILE=""
fi

if [ ! -z "$ENV_FILE" ]; then
    echo "Lade .env Datei: $ENV_FILE"
    # Lade Variablen aus .env Datei sicher
    set -a
    source "$ENV_FILE"
    set +a
else
    echo "Verwende Environment-Variablen aus der Umgebung"
fi

# Setze Build-Args mit Werten aus .env
BUILD_ARGS=()
if [ ! -z "$FLUXUI_MAIL" ]; then
    BUILD_ARGS+=("--build-arg" "FLUXUI_MAIL=$FLUXUI_MAIL")
    echo "Build-Arg gesetzt: FLUXUI_MAIL=$FLUXUI_MAIL"
fi
if [ ! -z "$FLUXUI_KEY" ]; then
    BUILD_ARGS+=("--build-arg" "FLUXUI_KEY=$FLUXUI_KEY")
    echo "Build-Arg gesetzt: FLUXUI_KEY=$FLUXUI_KEY"
fi
if [ ! -z "$REVERB_HOST" ]; then
    BUILD_ARGS+=("--build-arg" "REVERB_HOST=$REVERB_HOST")
    echo "Build-Arg gesetzt: REVERB_HOST=$REVERB_HOST"
fi

echo ""
echo "Starte Docker Build..."
echo ""

# Wechsle ins src-Verzeichnis (Build-Context)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SRC_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$SRC_DIR"

echo "Build-Context: $(pwd)"
echo "Dockerfile: docker/Dockerfile"
echo ""

# Docker Build mit Build-Args und Progress-Ausgabe
docker build --progress=plain "${BUILD_ARGS[@]}" -t ghcr.io/aleex1848/autodarts-stats:latest -f docker/Dockerfile .

echo ""
echo "Build erfolgreich! Starte Push..."
echo ""

docker push ghcr.io/aleex1848/autodarts-stats:latest

echo ""
echo "=== Fertig ==="