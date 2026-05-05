FROM php:8.4-apache

# Install build dependencies for PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    && docker-php-ext-install pdo pdo_mysql curl mbstring xml zip \
    && a2enmod rewrite \
    && a2enmod headers \
    && a2enmod ssl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

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
