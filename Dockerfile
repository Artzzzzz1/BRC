FROM php:8.3-apache

# PDO + mysqli for MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Let config/.htaccess work (blocks .txt/.log files); the default image ignores .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
 && echo "ServerName localhost" >> /etc/apache2/apache2.conf

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# Runs at container START (not build) because Railway can re-enable extra Apache MPM
# modules at runtime, which crashes Apache with "More than one MPM loaded".
# Also points Apache at the port Railway assigns via $PORT.
CMD a2dismod mpm_event mpm_worker > /dev/null 2>&1 || true; \
    rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*; \
    a2enmod mpm_prefork > /dev/null 2>&1 || true; \
    sed -i "s/Listen 80/Listen ${PORT:-80}/" /etc/apache2/ports.conf; \
    sed -i "s/:80>/:${PORT:-80}>/" /etc/apache2/sites-available/000-default.conf; \
    exec apache2-foreground
