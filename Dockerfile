FROM php:8.4-apache

# Install build dependencies for PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    && docker-php-ext-install pdo pdo_mysql curl mbstring xml zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable only necessary Apache modules (avoid MPM conflicts)
RUN a2enmod rewrite && \
    a2enmod headers && \
    a2enmod ssl

# Disable conflicting MPM modules if they exist
RUN a2dismod mpm_event mpm_worker 2>/dev/null || true

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
