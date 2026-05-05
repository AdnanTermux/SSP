FROM php:8.4-apache

# Install required PHP extensions
RUN apt-get update && apt-get install -y \
    php8.4-mysql \
    php8.4-curl \
    php8.4-mbstring \
    php8.4-xml \
    php8.4-zip \
    && docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite \
    && a2enmod headers \
    && a2enmod ssl

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
