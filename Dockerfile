#
# Icecast build stage (for later copy)
#
FROM azuracast/icecast-kh-ac:2.4.0-kh10-ac4 AS icecast

#
# Base image
#
FROM phusion/baseimage:0.11

# Set time zone
ENV TZ="UTC"
RUN echo $TZ > /etc/timezone \
    # Avoid ERROR: invoke-rc.d: policy-rc.d denied execution of start.
    && sed -i "s/^exit 101$/exit 0/" /usr/sbin/policy-rc.d 
    
# Common packages
RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -q -y --no-install-recommends apt-transport-https \
    # Web packages
    ca-certificates curl wget tar software-properties-common sudo zip unzip git rsync tzdata \
    nginx nginx-common nginx-extras \
    php7.2-fpm php7.2-cli php7.2-gd \
    php7.2-curl php7.2-xml php7.2-zip php7.2-bcmath \
    php7.2-mysqlnd php7.2-mbstring php7.2-intl php7.2-redis \
    # Base packages
    curl git ca-certificates tzdata tmpreaper \
    # Supervisord
    supervisor \
    # Icecast 
    libxml2 libxslt1-dev libvorbis-dev \
    && rm -rf /var/lib/apt/lists/*

# Create azurarelay user.
RUN adduser --home /var/azurarelay --disabled-password --gecos "" azurarelay \
    && usermod -aG docker_env azurarelay \
    && usermod -aG www-data azurarelay \
    && mkdir -p /var/azurarelay/www /var/azurarelay/stations /var/azurarelay/www_tmp \
    && chown -R azurarelay:azurarelay /var/azurarelay \
    && chmod -R 777 /var/azurarelay/www_tmp \
    && echo 'azurarelay ALL=(ALL) NOPASSWD: ALL' >> /etc/sudoers

# Install Supervisor
COPY ./supervisor/supervisord.conf /etc/supervisor/supervisord.conf

# Import Icecast-KH from build container
COPY --from=icecast /usr/local/bin/icecast /usr/local/bin/icecast
COPY --from=icecast /usr/local/share/icecast /usr/local/share/icecast

# Install nginx and configuration
COPY ./nginx/nginx.conf /etc/nginx/nginx.conf
COPY ./nginx/azurarelay.conf /etc/nginx/conf.d/azurarelay.conf

# Create nginx temp dirs
RUN mkdir -p /tmp/nginx_client /tmp/nginx_fastcgi_temp \
    && touch /tmp/nginx_client/.tmpreaper \
    && touch /tmp/nginx_fastcgi_temp/.tmpreaper \
    && chmod -R 777 /tmp/nginx_*

# Generate the dhparam.pem file (takes a long time)
RUN openssl dhparam -dsaparam -out /etc/nginx/dhparam.pem 4096

# Set certbot permissions
RUN mkdir -p /var/www/letsencrypt /var/lib/letsencrypt /etc/letsencrypt /var/log/letsencrypt \
    && chown -R azurarelay:azurarelay /var/www/letsencrypt /var/lib/letsencrypt /etc/letsencrypt /var/log/letsencrypt

# Install PHP 7.2
RUN mkdir -p /run/php
RUN touch /run/php/php7.2-fpm.pid

COPY ./php/php.ini /etc/php/7.2/fpm/conf.d/05-azurarelay.ini
COPY ./php/phpfpmpool.conf /etc/php/7.2/fpm/pool.d/www.conf

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer

# Set up first-run scripts and runit services
COPY ./scripts/ /usr/local/bin
COPY ./startup_scripts/ /etc/my_init.d/
COPY ./runit/ /etc/service/
COPY ./cron/ /etc/cron.d/

RUN chmod -R a+x /usr/local/bin \
    && chmod +x /etc/service/*/run \
    && chmod +x /etc/my_init.d/* \
    && chmod -R 600 /etc/cron.d/*

#
# START Operations as `azurarelay` user
#
USER azurarelay

# SSL self-signed cert generation
RUN openssl req -new -nodes -x509 -subj "/C=US/ST=Texas/L=Austin/O=IT/CN=localhost" \
    -days 365 -extensions v3_ca \
    -keyout /etc/letsencrypt/selfsigned.key \
	-out /etc/letsencrypt/selfsigned.crt

RUN ln -s /etc/letsencrypt/selfsigned.key /etc/letsencrypt/ssl.key \
    && ln -s /etc/letsencrypt/selfsigned.crt /etc/letsencrypt/ssl.crt

# Clone repo and set up repo
WORKDIR /var/azurarelay/www
VOLUME ["/var/azurarelay/www", "/var/azurarelay/stations", "/var/azurarelay/www_tmp", "/etc/letsencrypt"]

COPY --chown=azurarelay:azurarelay ./www .

RUN rm -rf vendor \
    && composer install --no-dev

#
# END Operations as `azurarelay` user
#

USER root
CMD ["/sbin/my_init"]