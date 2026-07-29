# Production Dockerfile for Laravel Backend on DigitalOcean App Platform
FROM php:8.2-apache

# 1. Install system dependencies and build libraries for PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    zip \
    unzip \
    ssl-cert \
    libssl-dev \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

# 2. Install standard PHP extensions
RUN docker-php-ext-install pdo pdo_mysql pgsql pdo_pgsql mbstring exif pcntl bcmath gd zip opcache

# 3. Install and enable MongoDB PHP extension via PECL
RUN pecl install mongodb && docker-php-ext-enable mongodb

# 4. Enable Apache mod_rewrite for Laravel routing
RUN a2enmod rewrite

# 5. Set Apache DocumentRoot to /var/www/html/public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 6. Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 7. Set working directory
WORKDIR /var/www/html

# 8. Copy application files
COPY . /var/www/html

# 9. Install composer dependencies (no dev dependencies for production)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# 10. Set directory permissions for Laravel storage and cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# 11. Expose Apache port 80
EXPOSE 80

# 12. Startup command: Cache configurations and start Apache
CMD ["sh", "-c", "php artisan config:cache && php artisan route:cache && php artisan view:cache && apache2-foreground"]
