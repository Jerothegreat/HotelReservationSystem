FROM php:8.2-apache
RUN a2enmod rewrite
WORKDIR /var/www/html
COPY . .
RUN chmod +x /var/www/html/apache-port.sh \
	&& cp /var/www/html/apache-port.sh /usr/local/bin/apache-port.sh
EXPOSE 80
CMD ["apache-port.sh"]