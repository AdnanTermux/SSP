FROM php:8.4-apache

# Install build dependencies for PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    && docker-php-ext-install pdo pdo_mysql curl mbstring xml zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Remove Apache MPM modules to prevent conflicts
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf

# Ensure only mpm_prefork is enabled
RUN a2enmod mpm_prefork rewrite headers

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
