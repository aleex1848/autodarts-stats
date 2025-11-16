FROM webdevops/php-nginx:8.4-alpine
ENV WEB_DOCUMENT_ROOT=/app/public
ENV PHP_DISMOD=bz2,calendar,ffi,gettext,sockets,sysvmsg,sysvsm,sysvshm,shmop,xsl,apcu,vips,yaml,mongodb,amqp
RUN apk add nano nodejs npm
COPY --chown=1000:1000 . /app
WORKDIR /opt/docker
COPY --chown=1000:1000 docker/php-nginx-app/ .
COPY --chown=1000:1000 docker/php-nginx-cron/ .
COPY --chown=1000:1000 docker/php-nginx-queue/ .
#COPY --chown=1000:1000 docker/php-nginx-websockets/ .

