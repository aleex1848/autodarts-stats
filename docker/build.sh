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

# Parse Kommandozeilen-Argumente
BUILD_TYPE="both"
if [ "$1" = "--dev" ]; then
    BUILD_TYPE="dev"
elif [ "$1" = "--prod" ]; then
    BUILD_TYPE="prod"
elif [ ! -z "$1" ]; then
    echo "Unbekannter Parameter: $1"
    echo "Verwendung: $0 [--dev|--prod]"
    echo "  --dev:  Nur Development-Build (mit Dev-Dependencies)"
    echo "  --prod: Nur Production-Build (ohne Dev-Dependencies)"
    echo "  (ohne Parameter: Beide Builds)"
    exit 1
fi

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

# Funktion zum Erstellen eines Builds
build_image() {
    local install_dev_deps=$1
    local tag=$2
    local build_name=$3
    
    echo "=== $build_name Build ==="
    echo "INSTALL_DEV_DEPS=$install_dev_deps"
    echo "Tag: $tag"
    echo ""
    
    docker build --progress=plain \
        "${BUILD_ARGS[@]}" \
        --build-arg "INSTALL_DEV_DEPS=$install_dev_deps" \
        -t "$tag" \
        -f docker/Dockerfile .
    
    echo ""
    echo "$build_name Build erfolgreich!"
    echo ""
}

# Funktion zum Pushen eines Images
push_image() {
    local tag=$1
    echo "Starte Push für $tag..."
    docker push "$tag"
    echo "Push erfolgreich!"
    echo ""
}

# Erstelle Builds basierend auf BUILD_TYPE
if [ "$BUILD_TYPE" = "dev" ] || [ "$BUILD_TYPE" = "both" ]; then
    build_image "true" "ghcr.io/aleex1848/autodarts-stats:dev" "Development"
fi

if [ "$BUILD_TYPE" = "prod" ] || [ "$BUILD_TYPE" = "both" ]; then
    build_image "false" "ghcr.io/aleex1848/autodarts-stats:latest" "Production"
fi

echo "=== Starte Push ==="
echo ""

# Pushe Images basierend auf BUILD_TYPE
if [ "$BUILD_TYPE" = "dev" ] || [ "$BUILD_TYPE" = "both" ]; then
    push_image "ghcr.io/aleex1848/autodarts-stats:dev"
fi

if [ "$BUILD_TYPE" = "prod" ] || [ "$BUILD_TYPE" = "both" ]; then
    push_image "ghcr.io/aleex1848/autodarts-stats:latest"
fi

echo "=== Fertig ==="