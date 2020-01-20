#!/bin/bash
set -e
source /bd_build/buildconfig
set -x

$minimal_apt_get_install sudo

adduser --home /var/azurarelay --disabled-password --gecos "" azurarelay

usermod -aG docker_env azurarelay
usermod -aG www-data azurarelay

mkdir -p /var/azurarelay/www /var/azurarelay/stations /var/azurarelay/www_tmp

chown -R azurarelay:azurarelay /var/azurarelay
chmod -R 777 /var/azurarelay/www_tmp

echo 'azurarelay ALL=(ALL) NOPASSWD: ALL' >> /etc/sudoers
