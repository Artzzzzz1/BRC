FROM php:8.3-apache

# PDO + mysqli for MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Let config/.htaccess work (blocks .txt/.log files), default image ignores .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# Railway tells the app which port to use via $PORT; Apache must listen on it.
CMD sed -i "s/Listen 80/Listen ${PORT:-80}/" /etc/apache2/ports.conf \
 && sed -i "s/:80>/:${PORT:-80}>/" /etc/apache2/sites-available/000-default.conf \
 && apache2-foreground
