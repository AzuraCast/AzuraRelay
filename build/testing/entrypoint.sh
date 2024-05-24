#!/bin/bash

# Write environment variables to script
declare -p | grep -Ev 'BASHOPTS|BASH_VERSINFO|EUID|PPID|SHELLOPTS|UID' > /container.env
chmod 744 /container.env

# Clear Temp
shopt -s dotglob
rm -rf /var/app/www_tmp/*

# Composer install
cd /var/app/www

su-exec app composer install

exec "$@"
