#
# Icecast-KH with AzuraCast customizations build step
#
FROM ghcr.io/azuracast/icecast-kh-ac:2024-05-24-alpine AS icecast

#
# Supercronic
#

FROM golang:1-alpine3.20 AS supercronic

RUN go install github.com/aptible/supercronic@latest

#
# Main Image
#
FROM php:8.4-cli-alpine3.20 AS base

ENV TZ=UTC

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions @composer gd curl xml zip mbstring

RUN apk add --no-cache zip git curl bash supervisor nginx su-exec \
    libxml2 libxslt libvorbis

# Import Icecast-KH from build container
COPY --from=icecast /usr/local/bin/icecast /usr/local/bin/icecast
COPY --from=icecast /usr/local/share/icecast /usr/local/share/icecast

# Import supercronic
COPY --from=supercronic /go/bin/supercronic /usr/local/bin/supercronic

# Set up App user
RUN mkdir -p /var/app/www \
    && addgroup -g 1000 app \
    && adduser -u 1000 -G app -h /var/app/ -s /bin/sh -D app \
    && addgroup app www-data \
    && mkdir -p /var/app/www /var/app/stations /var/app/www_tmp /var/app/acme \
       /etc/my_init.d /run/supervisord \
    && chown -R app:app /var/app

COPY ./build/php.ini /usr/local/etc/php/php.ini
COPY ./build/supervisord.conf /etc/supervisord.conf
COPY ./build/crontab /var/app/crontab
COPY ./build/scripts /usr/local/bin

COPY ./build/nginx/proxy_params.conf /etc/nginx/proxy_params
COPY ./build/nginx/nginx.conf /etc/nginx/nginx.conf
COPY ./build/nginx/azurarelay.conf /etc/nginx/sites-enabled/default.conf

RUN chmod a+x /usr/local/bin/*

EXPOSE 80 8000 8010 8020 8030 8040 8050 8060 8070 8090 \
    8100 8110 8120 8130 8140 8150 8160 8170 8180 8190 \
    8200 8210 8220 8230 8240 8250 8260 8270 8280 8290 \
    8300 8310 8320 8330 8340 8350 8360 8370 8380 8390 \
    8400 8410 8420 8430 8440 8450 8460 8470 8480 8490

#
# Development Build
#
FROM base AS development

COPY ./build/dev/entrypoint.sh /var/app/entrypoint.sh
RUN chmod a+x /var/app/entrypoint.sh

RUN apk add --no-cache shadow

WORKDIR /var/app/www

ENV APPLICATION_ENV=development

ENTRYPOINT ["/var/app/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]

# 
# Testing Build
#
FROM base AS testing

COPY ./build/testing/entrypoint.sh /var/app/entrypoint.sh
RUN chmod a+x /var/app/entrypoint.sh

ENV APPLICATION_ENV=testing

WORKDIR /var/app/www
COPY --chown=app:app . .

ENTRYPOINT ["/var/app/entrypoint.sh"]
CMD ["app_ci"]

#
# Production Build
#
FROM base AS production

COPY ./build/prod/entrypoint.sh /var/app/entrypoint.sh
RUN chmod a+x /var/app/entrypoint.sh

VOLUME ["/var/app/acme"]

USER app

WORKDIR /var/app/www
COPY --chown=app:app . .

RUN composer install --no-dev --no-ansi --no-autoloader --no-interaction \
    && composer dump-autoload --optimize --classmap-authoritative \
    && composer clear-cache

USER root

ENV APPLICATION_ENV=production

ENTRYPOINT ["/var/app/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
