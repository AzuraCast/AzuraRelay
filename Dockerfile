#
# Icecast build stage (for later copy)
#
FROM azuracast/icecast-kh-ac:latest AS icecast

#
# Base image
#
FROM ubuntu:bionic

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
CMD ["/usr/local/bin/my_init"]
