FROM php:8.2-apache

# Enforce a single Apache MPM: keep only prefork (required for mod_php).
RUN set -eux; \
 a2dismod mpm_event mpm_worker mpm_prefork 2>/dev/null || true; \
 rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf; \
 rm -f /etc/apache2/mods-available/mpm_event.* /etc/apache2/mods-available/mpm_worker.* /etc/apache2/mods-available/mpm_itk.* /etc/apache2/mods-available/mpm_winnt.*; \
 a2enmod mpm_prefork rewrite; \
 rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*; \
 test -e /etc/apache2/mods-available/mpm_prefork.load; \
 test -e /etc/apache2/mods-available/mpm_prefork.conf; \
 ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load; \
 ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf; \
 test "$(find /etc/apache2/mods-enabled -maxdepth 1 \( -type f -o -type l \) -name 'mpm_*.*' | wc -l)" -eq 2; \
 test ! -e /etc/apache2/mods-enabled/mpm_event.load; \
 test ! -e /etc/apache2/mods-enabled/mpm_event.conf

# Apache config: allow .htaccess overrides
RUN echo '<Directory /var/www/html>\n\
	Options Indexes FollowSymLinks\n\
	AllowOverride All\n\
	Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY . .

# Copy and wire up the entrypoint that handles Railway's dynamic PORT
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
 && chmod +x /usr/local/bin/docker-entrypoint.sh \
 && chown -R www-data:www-data /var/www/html

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]