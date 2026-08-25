#!/bin/sh
# Railway injects its own Apache config at container start (like Heroku),
# which can re-enable mpm_event alongside mpm_prefork and break startup with
# "AH00534: More than one MPM loaded". Fix the MPM at runtime, and bind
# Apache to Railway's PORT (defaults to 80 for local/compose runs).
set -e

PORT="${PORT:-80}"

# Ensure only mpm_prefork (required by mod_php) is loaded.
a2dismod mpm_event 2>/dev/null || true
a2dismod mpm_worker 2>/dev/null || true
rm -f /etc/apache2/mods-enabled/mpm_event.* \
      /etc/apache2/mods-enabled/mpm_worker.* 2>/dev/null || true
a2enmod mpm_prefork

# Bind Apache to the requested port.
sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground