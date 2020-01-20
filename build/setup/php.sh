#!/bin/bash
set -e
source /bd_build/buildconfig
set -x

add-apt-repository -y ppa:ondrej/php
apt-get update

$minimal_apt_get_install php7.4-fpm php7.4-cli php7.4-gd \
    php7.4-curl php7.4-xml php7.4-zip php7.4-bcmath \
    php7.4-mbstring php7.4-intl php7.4-redis 

# Copy PHP configuration
mkdir -p /run/php
touch /run/php/php7.4-fpm.pid

cp /bd_build/php/php.ini /etc/php/7.4/fpm/conf.d/05-app.ini
cp /bd_build/php/phpfpmpool.conf /etc/php/7.4/fpm/pool.d/www.conf

# Install Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer
