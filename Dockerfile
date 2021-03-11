#
# Icecast build stage (for later copy)
#
FROM azuracast/icecast-kh-ac:latest AS icecast

#
# Base image
#
FROM ubuntu:focal

# Set time zone
ENV TZ="UTC"

# Import Icecast-KH from build container
COPY --from=icecast /usr/local/bin/icecast /usr/local/bin/icecast
COPY --from=icecast /usr/local/share/icecast /usr/local/share/icecast

# Run base build process
COPY ./build/ /bd_build

RUN chmod a+x /bd_build/*.sh \
    && /bd_build/prepare.sh \
    && /bd_build/add_user.sh \
    && /bd_build/setup.sh \
    && /bd_build/cleanup.sh \
    && rm -rf /bd_build

#
# START Operations as `azurarelay` user
#
USER azurarelay

# Clone repo and set up repo
WORKDIR /var/azurarelay/www
VOLUME ["/var/azurarelay/stations", "/var/azurarelay/www_tmp", "/etc/letsencrypt"]

COPY --chown=azurarelay:azurarelay ./www/composer.json ./www/composer.lock ./
RUN composer install  \
    --ignore-platform-reqs \
    --no-ansi \
    --no-autoloader \
    --no-interaction \
    --no-scripts

# We need to copy our whole application so that we can generate the autoload file inside the vendor folder.
COPY --chown=azurarelay:azurarelay ./www .

RUN composer dump-autoload --optimize --classmap-authoritative

#
# END Operations as `azurarelay` user
#

USER root

EXPOSE 80 8000 8010 8020 8030 8040 8050 8060 8070 8090 \
        8100 8110 8120 8130 8140 8150 8160 8170 8180 8190 \
        8200 8210 8220 8230 8240 8250 8260 8270 8280 8290 \
        8300 8310 8320 8330 8340 8350 8360 8370 8380 8390 \
        8400 8410 8420 8430 8440 8450 8460 8470 8480 8490

# Nginx Proxy environment variables.
ENV VIRTUAL_HOST="azurarelay.local" \
    HTTPS_METHOD="noredirect" \
    APPLICATION_ENV="production"

CMD ["/usr/local/bin/my_init"]
