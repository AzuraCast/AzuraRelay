#
# Icecast build step
#
FROM alpine:3.16 AS icecast

RUN apk add --no-cache curl git ca-certificates \
    alpine-sdk libxml2 libxslt-dev libvorbis-dev libssl3 libcurl

WORKDIR /tmp/install_icecast

RUN curl -fsSL -o icecast.tar.gz https://github.com/AzuraCast/icecast-kh-ac/archive/refs/tags/2.4.0-kh15-ac2.tar.gz \
    && tar -xzvf icecast.tar.gz --strip-components=1 \
    && ./configure \
    && make \
    && make install

#
# Main Image
#
FROM php:8.1-cli-alpine3.16

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions @composer gd curl xml zip bcmath mbstring intl

RUN apk add --no-cache zip git curl bash supervisor nginx su-exec \
    libxml2 libxslt libvorbis

# Import Icecast-KH from build container
COPY --from=icecast /usr/local/bin/icecast /usr/local/bin/icecast
COPY --from=icecast /usr/local/share/icecast /usr/local/share/icecast

# Set up App user
RUN mkdir -p /var/app/www \
    && addgroup -g 1000 app \
    && adduser -u 1000 -G app -h /var/app/ -s /bin/sh -D app \
    && addgroup app www-data \
    && mkdir -p /var/app/www /var/app/stations /var/app/www_tmp /var/app/acme \
       /etc/my_init.d /run/supervisord \
    && chown -R app:app /var/app

COPY ./build/php.ini /usr/local/etc/php/php.ini
COPY ./build/supervisord.conf /etc/supervisor/supervisord.conf
COPY ./build/crontab /var/spool/cron/crontabs/app
COPY ./build/startup_scripts /etc/my_init.d
COPY ./build/scripts /usr/local/bin
COPY ./build/nginx/proxy_params.conf /etc/nginx/proxy_params
COPY ./build/nginx/nginx.conf /etc/nginx/nginx.conf
COPY ./build/nginx/azurarelay.conf /etc/nginx/sites-enabled/default.conf

RUN chmod a+x /usr/local/bin/*

VOLUME ["/var/app/acme"]

#
# START Operations as `app` user
#
USER app

# Clone repo and set up repo
WORKDIR /var/app/www

COPY --chown=app:app ./www/composer.json ./www/composer.lock ./
RUN composer install  \
    --ignore-platform-reqs \
    --no-ansi \
    --no-autoloader \
    --no-interaction \
    --no-scripts

# We need to copy our whole application so that we can generate the autoload file inside the vendor folder.
COPY --chown=app:app ./www .

RUN composer dump-autoload --optimize --classmap-authoritative

#
# END Operations as `app` user
#

USER root

EXPOSE 80 8000 8010 8020 8030 8040 8050 8060 8070 8090 \
        8100 8110 8120 8130 8140 8150 8160 8170 8180 8190 \
        8200 8210 8220 8230 8240 8250 8260 8270 8280 8290 \
        8300 8310 8320 8330 8340 8350 8360 8370 8380 8390 \
        8400 8410 8420 8430 8440 8450 8460 8470 8480 8490

ENTRYPOINT ["/usr/local/bin/my_init"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]
