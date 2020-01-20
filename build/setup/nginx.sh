#!/bin/bash
set -e
source /bd_build/buildconfig
set -x

$minimal_apt_get_install nginx nginx-common nginx-extras

# Install nginx and configuration
cp /bd_build/nginx/nginx.conf /etc/nginx/nginx.conf
cp /bd_build/nginx/azurarelay.conf /etc/nginx/conf.d/azurarelay.conf

# Create nginx temp dirs
mkdir -p /tmp/nginx_client /tmp/nginx_fastcgi_temp
touch /tmp/nginx_client/.tmpreaper
touch /tmp/nginx_fastcgi_temp/.tmpreaper
chmod -R 777 /tmp/nginx_*

# Generate the dhparam.pem file (takes a long time)
openssl dhparam -dsaparam -out /etc/nginx/dhparam.pem 4096
