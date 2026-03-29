#!/bin/sh
set -eu

PORT="${PORT:-80}"

# Railway injects PORT dynamically -- patch Apache to use it
sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" \
    /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
