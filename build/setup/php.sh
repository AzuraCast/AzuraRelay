#!/bin/bash
set -e
source /bd_build/buildconfig
set -x

PHP_VERSION=8.1

add-apt-repository -y ppa:ondrej/php
apt-get update

$minimal_apt_get_install php${PHP_VERSION}-fpm php${PHP_VERSION}-cli php${PHP_VERSION}-gd \
  php${PHP_VERSION}-curl php${PHP_VERSION}-xml php${PHP_VERSION}-zip php${PHP_VERSION}-bcmath \
  php${PHP_VERSION}-gmp php${PHP_VERSION}-mysqlnd php${PHP_VERSION}-mbstring php${PHP_VERSION}-intl \
  php${PHP_VERSION}-redis php${PHP_VERSION}-maxminddb php${PHP_VERSION}-xdebug \
  mariadb-client

# Copy PHP configuration
mkdir -p /run/php
touch /run/php/php${PHP_VERSION}-fpm.pid

echo "PHP_VERSION=${PHP_VERSION}" >>/etc/php/.version

cp /bd_build/php/php.ini.tmpl /etc/php/${PHP_VERSION}/fpm/05-azuracast.ini.tmpl
cp /bd_build/php/www.conf.tmpl /etc/php/${PHP_VERSION}/fpm/www.conf.tmpl

# Install Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer
