FROM php:8.2-apache

    # Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql
RUN docker-php-ext-enable pdo_mysql

# Copy project files into Apache server directory
COPY . /var/www/html/

# Enable mod_rewrite if you're using .htaccess
RUN a2enmod rewrite

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html

# Expose Apache default port
EXPOSE 80
