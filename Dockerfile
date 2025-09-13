# --- Stage 1: Build dependencies with Composer ---
    FROM composer:2.7 as build

    WORKDIR /app
    
    COPY composer.json composer.lock ./
    RUN composer install --no-dev --no-scripts
    
    
    # --- Stage 2: Production environment with Apache and PHP ---
    FROM php:8.2-apache
    
    WORKDIR /var/www/html
    
    # Install system dependencies and PHP extensions
    RUN apt-get update && apt-get install -y \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libzip-dev \
        libonig-dev \
        git \
        curl \
        unzip \
        --no-install-recommends && rm -rf /var/lib/apt/lists/*
    
    # Install PHP extensions
    RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
        docker-php-ext-install -j$(nproc) pdo pdo_mysql gd exif opcache zip mbstring
    
    # ✅ Install Composer here so it's available during dump-autoload
    RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    
    # Copy app code and vendor directory
    COPY . .
    COPY --from=build /app/vendor /var/www/html/vendor
    
    # ✅ Now that Composer is installed and vendor is copied, run dump-autoload
    RUN composer dump-autoload --optimize --no-dev
    
    # Set correct permissions
    RUN chown -R www-data:www-data /var/www/html
    
    # Configure Apache
    RUN echo '<VirtualHost *:80>\n\
        DocumentRoot /var/www/html/public\n\
        <Directory /var/www/html/public>\n\
            AllowOverride All\n\
            Require all granted\n\
        </Directory>\n\
        ErrorLog ${APACHE_LOG_DIR}/error.log\n\
        CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
    </VirtualHost>' > /etc/apache2/sites-available/000-default.conf
    
    RUN a2enmod rewrite
    
    EXPOSE 80
    CMD ["apache2-foreground"]
    