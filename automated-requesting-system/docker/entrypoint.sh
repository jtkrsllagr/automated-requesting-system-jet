#!/bin/sh
set -eu

cert_file=/etc/ars-certs/ars-selfsigned.crt
key_file=/etc/ars-certs/ars-selfsigned.key
secret_file=/var/www/automated-requesting-system/storage/realtime.secret
print_archive_dir=/var/www/automated-requesting-system/storage/print-archives

mkdir -p "$print_archive_dir"
chown -R www-data:www-data "$print_archive_dir"
chmod 750 "$print_archive_dir"

if [ ! -s "$cert_file" ] || [ ! -s "$key_file" ]; then
    mkdir -p /etc/ars-certs
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout "$key_file" \
        -out "$cert_file" \
        -subj "/C=PH/ST=NCR/L=Manila/O=AutomatedRequestingSystem/CN=192.168.100.108" \
        -addext "subjectAltName=IP:192.168.100.108,DNS:localhost"
fi

if [ ! -s "$secret_file" ]; then
    openssl rand -hex 32 > "$secret_file"
    chmod 644 "$secret_file"
fi

exec docker-php-entrypoint apache2-foreground