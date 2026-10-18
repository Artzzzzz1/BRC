FROM php:8.3-apache

# PDO + mysqli for MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Let config/.htaccess work (blocks .txt/.log files); the default image ignores .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
 && echo "ServerName localhost" >> /etc/apache2/apache2.conf

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# Startup script (strips Windows line endings just in case, then makes it executable)
COPY railway-entrypoint.sh /usr/local/bin/railway-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/railway-entrypoint.sh \
 && chmod +x /usr/local/bin/railway-entrypoint.sh \
 && rm -f /var/www/html/Dockerfile /var/www/html/railway-entrypoint.sh /var/www/html/.dockerignore

CMD ["/usr/local/bin/railway-entrypoint.sh"]
