#!/bin/bash
set -e
source /bd_build/buildconfig
set -x

$minimal_apt_get_install nginx nginx-common nginx-extras openssl

# Install nginx and configuration
cp /bd_build/nginx/proxy_params.conf /etc/nginx/proxy_params
cp /bd_build/nginx/nginx.conf.tmpl /etc/nginx/nginx.conf.tmpl
cp /bd_build/nginx/azurarelay.conf.tmpl /etc/nginx/azurarelay.conf.tmpl

# Create nginx temp dirs
mkdir -p /tmp/app_nginx_client /tmp/app_fastcgi_temp
touch /tmp/app_nginx_client/.tmpreaper
touch /tmp/app_fastcgi_temp/.tmpreaper
chmod -R 777 /tmp/app_*
