FROM php:8.2-apache

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql
RUN docker-php-ext-enable pdo_mysql

# Enable mod_rewrite if you're using .htaccess
RUN a2enmod rewrite

# Copy project files into Apache server directory
COPY . /var/www/html/

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html

# Replace the default Apache config to listen on $PORT
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -i 's/:80/:${PORT}/' /etc/apache2/sites-available/000-default.conf

# Expose the port (Render ignores this, but still good practice)
EXPOSE 10000

# Start Apache (Render will set $PORT automatically)
CMD ["sh", "-c", "apache2-foreground"]
