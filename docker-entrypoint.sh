#!/bin/sh
set -eu

PORT="${PORT:-80}"

# Railway injects PORT dynamically -- patch Apache to use it
sed -ri "s/^Listen [0-9]+$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s#<VirtualHost \*:[0-9]+>#<VirtualHost *:${PORT}>#" /etc/apache2/sites-available/000-default.conf

echo "[startup] PORT=${PORT}"
echo "[startup] Enabled MPM module files:"
ls -1 /etc/apache2/mods-enabled/mpm_* 2>/dev/null || echo "[startup] none"
echo "[startup] Apache module dump (MPM only):"
apache2ctl -M 2>&1 | grep -i "mpm_" || true

exec apache2-foreground
