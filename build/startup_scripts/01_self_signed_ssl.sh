#!/bin/bash

mkdir -p /var/app/acme/challenges || true

if [ -f /var/app/acme/default.crt ]; then
    rm -rf /var/app/acme/default.key || true
    rm -rf /var/app/acme/default.crt || true
fi

# Generate a self-signed certificate if one doesn't exist in the certs path.
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
