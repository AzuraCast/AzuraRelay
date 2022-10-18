#!/bin/bash

# Write environment variables to script
declare -p | grep -Ev 'BASHOPTS|BASH_VERSINFO|EUID|PPID|SHELLOPTS|UID' > /container.env
chmod 744 /container.env

# SSL Cert Management
mkdir -p /var/app/acme/challenges || true

if [ -f /var/app/acme/default.crt ]; then
    rm -rf /var/app/acme/default.key || true
    rm -rf /var/app/acme/default.crt || true
fi

if [ ! -f /var/app/acme/default.crt ]; then
    echo "Generating self-signed certificate..."

    openssl req -new -nodes -x509 -subj "/C=US/ST=Texas/L=Austin/O=IT/CN=localhost" \
        -days 365 -extensions v3_ca \
        -keyout /var/app/acme/default.key \
        -out /var/app/acme/default.crt
fi

if [ ! -f /var/app/acme/ssl.crt ]; then
    ln -s /var/app/acme/default.key /var/app/acme/ssl.key
    ln -s /var/app/acme/default.crt /var/app/acme/ssl.crt
fi

chown -R app:app /var/app/acme || true
chmod -R u=rwX,go=rX /var/app/acme || true

# Clear Temp
shopt -s dotglob
rm -rf /var/app/www_tmp/*

# Run Command
if [ "$1" = '--no-main-command' ]; then
    exec supervisord -c /etc/supervisor/supervisord.conf --nodaemon
fi

supervisord -c /etc/supervisor/supervisord.conf

exec "$@"
