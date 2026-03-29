#!/bin/sh
set -eu

PORT="${PORT:-80}"

# Railway injects PORT at runtime; align Apache listeners with it.
sed -ri "s/^Listen [0-9]+$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s#<VirtualHost \*:[0-9]+>#<VirtualHost *:${PORT}>#" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground