FROM composer:2.9 AS vendor
ARG FLUXUI_MAIL=myMail
ARG FLUXUI_KEY=myKey
ARG INSTALL_DEV_DEPS=false
COPY database/ database/ 
COPY composer.json composer.json
COPY composer.lock composer.lock
#in packages sind meine custom packages
#COPY packages/ packages/ 
RUN composer config http-basic.composer.fluxui.dev $FLUXUI_MAIL $FLUXUI_KEY
RUN if [ "$INSTALL_DEV_DEPS" = "true" ]; then \
        composer install \
            --ignore-platform-reqs \
            --no-interaction \
            --no-plugins \
            --no-scripts \
            --prefer-dist; \
    else \
        composer install \
            --ignore-platform-reqs \
            --no-interaction \
            --no-plugins \
            --no-scripts \
            --prefer-dist \
            --no-dev; \
    fi

FROM node:25 AS frontend
ARG REVERB_HOST=myHost
# Stelle sicher, dass das public Verzeichnis existiert (Vite benötigt es für den Build)
RUN mkdir -p /app/public
COPY package.json \
    vite.config.js \
    package-lock.json \
    /app/
COPY --chown=1000:1000 --from=vendor /app/vendor/livewire/flux/ /app/vendor/livewire/flux/
COPY resources/js/ /app/resources/js/
COPY resources/css/ /app/resources/css/
# Kopiere public/index.php für Vite (falls benötigt)
COPY public/index.php /app/public/index.php
ENV VITE_REVERB_HOST=$REVERB_HOST
ENV VITE_REVERB_PORT=443
ENV VITE_REVERB_SCHEME=https
ENV REVERB_HOST=$REVERB_HOST
ENV REVERB_PORT=443
ENV REVERB_SCHEME=https
ENV NODE_ENV=production
WORKDIR /app
# Debug-Ausgabe für REVERB_HOST (maskiert für Sicherheit)
RUN echo "=== Frontend Build Debug ===" && \
    if [ -n "$REVERB_HOST" ]; then \
        REVERB_HOST_LENGTH=$(echo -n "$REVERB_HOST" | wc -c); \
        REVERB_HOST_PREVIEW=$(echo "$REVERB_HOST" | cut -c1-3); \
        REVERB_HOST_SUFFIX=$(echo "$REVERB_HOST" | rev | cut -c1-3 | rev); \
        echo "✓ REVERB_HOST ist gesetzt"; \
        echo "  Länge: ${REVERB_HOST_LENGTH} Zeichen"; \
        echo "  Vorschau: ${REVERB_HOST_PREVIEW}...${REVERB_HOST_SUFFIX}"; \
        echo "  Vollständiger Wert wird für Build verwendet"; \
    else \
        echo "✗ REVERB_HOST ist NICHT gesetzt (verwendet Default: myHost)"; \
    fi && \
    echo "VITE_REVERB_HOST=${VITE_REVERB_HOST}" && \
    echo "==============================" && \
    echo "=== Verfügbare VITE_ Umgebungsvariablen ===" && \
    env | grep "^VITE_" || echo "Keine VITE_ Variablen gefunden" && \
    echo "==========================================="
RUN npm install && npm run build
# Verifiziere, dass die Build-Artefakte erstellt wurden (inkl. manifest.json)
RUN echo "=== Build-Artefakte im frontend-Stage ===" && \
    ls -lah /app/public/build/ && \
    echo "=== Prüfe manifest.json ===" && \
    test -f /app/public/build/manifest.json && echo "✓ manifest.json gefunden" || echo "✗ manifest.json fehlt!"

FROM webdevops/php-nginx:8.4-alpine
ENV WEB_DOCUMENT_ROOT=/app/public
ENV PHP_DISMOD=bz2,calendar,ffi,gettext,sockets,sysvmsg,sysvsm,sysvshm,shmop,xsl,apcu,vips,yaml,mongodb,amqp
RUN apk add nano nodejs npm
# Kopiere zuerst den Source-Code (ohne public/build, da es in .dockerignore ist)
COPY --chown=1000:1000 . /app
COPY --chown=1000:1000 --from=vendor /app/vendor/ /app/vendor/
# Entferne eventuell vorhandenes leeres public/build Verzeichnis aus dem Source-Code
RUN rm -rf /app/public/build
# Kopiere die Build-Artefakte NACH dem Source-Code, damit sie nicht überschrieben werden
# Stelle sicher, dass das Verzeichnis existiert und kopiere alle Build-Artefakte inkl. manifest.json
RUN mkdir -p /app/public/build
COPY --chown=1000:1000 --from=frontend /app/public/build/ /app/public/build/
# Verifiziere, dass die Build-Artefakte korrekt kopiert wurden
RUN echo "=== Build-Artefakte im finalen Image ===" && \
    ls -lah /app/public/build/ && \
    echo "=== Prüfe manifest.json ===" && \
    test -f /app/public/build/manifest.json && echo "✓ manifest.json gefunden" || echo "✗ manifest.json fehlt!" && \
    echo "=== Prüfe CSS/JS Dateien ===" && \
    ls -lah /app/public/build/assets/ 2>/dev/null || echo "⚠ assets Verzeichnis fehlt"
# node_modules werden nicht benötigt im Produktions-Image, da die Assets bereits gebaut sind
# COPY --chown=1000:1000 --from=frontend /app/node_modules/ /app/node_modules/
WORKDIR /opt/docker
COPY --chown=1000:1000 docker/php-nginx-app/ .
COPY --chown=1000:1000 docker/php-nginx-cron/ .
COPY --chown=1000:1000 docker/php-nginx-queue/ .
COPY --chown=1000:1000 docker/php-nginx-websockets/ .
WORKDIR /app
