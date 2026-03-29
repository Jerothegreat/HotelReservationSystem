FROM php:8.2-apache

# Fix MPM conflict: disable all common MPM modules, then enable only prefork (required for mod_php)
RUN a2dismod mpm_event mpm_worker mpm_prefork 2>/dev/null || true \
 && a2enmod mpm_prefork rewrite

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