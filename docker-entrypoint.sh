#!/bin/sh
set -eu

PORT="${PORT:-80}"

# Force a single MPM at runtime to avoid stale/conflicting module state.
rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf
test -e /etc/apache2/mods-available/mpm_prefork.load
test -e /etc/apache2/mods-available/mpm_prefork.conf
ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf

# Railway injects PORT dynamically -- patch Apache to use it
sed -ri "s/^Listen [0-9]+$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s#<VirtualHost \*:[0-9]+>#<VirtualHost *:${PORT}>#" /etc/apache2/sites-available/000-default.conf

echo "[startup] PORT=${PORT}"
echo "[startup] Enabled MPM module files:"
ls -1 /etc/apache2/mods-enabled/mpm_* 2>/dev/null || echo "[startup] none"
echo "[startup] Apache module dump (MPM only):"
apache2ctl -M 2>&1 | grep -i "mpm_" || true

apache2ctl -t

exec apache2-foreground
