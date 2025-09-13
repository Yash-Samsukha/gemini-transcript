# --- Stage 1: Build Stage for Dependencies ---
    FROM composer:2.7 as build

    # Set the working directory for the build stage
    WORKDIR /app
    
    # Copy composer files and install dependencies
    COPY composer.json composer.lock ./
    # We run composer install without scripts here to avoid errors
    # related to the missing artisan file in the build stage.
    RUN composer install --no-dev --no-scripts
    
    # --- Stage 2: Production Stage ---
    FROM php:8.2-apache
    
    # Set working directory for the production stage
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
        # Clean up APT cache to reduce image size
        --no-install-recommends && rm -rf /var/lib/apt/lists/*
    
    # Install required PHP extensions
    RUN docker-php-ext-install pdo pdo_mysql gd exif opcache zip mbstring
    
    # Configure GD extension with FreeType and JPEG support
    RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
        && docker-php-ext-install -j$(nproc) gd
    
    # Copy application files from your repository
    COPY . .
    
    # Copy the vendor directory from the build stage
    COPY --from=build /app/vendor /var/www/html/vendor
    
    # Manually dump the autoloader after copying vendor files
    # This is the crucial step to fix your error
    # We run it here because the artisan file is now available in the container.
    RUN composer dump-autoload --optimize --no-dev
    
    # Give Apache ownership of the project directory for permissions
    RUN chown -R www-data:www-data /var/www/html
    
    # Set up Apache to use the 'public' directory as the web root
    RUN echo '<VirtualHost *:80>\n\
        DocumentRoot /var/www/html/public\n\
        <Directory /var/www/html/public>\n\
            AllowOverride All\n\
            Require all granted\n\
        </Directory>\n\
        ErrorLog ${APACHE_LOG_DIR}/error.log\n\
        CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
    </VirtualHost>' > /etc/apache2/sites-available/000-default.conf
    
    # Enable Apache rewrite module
    RUN a2enmod rewrite
    
    # Expose port 80 and start Apache
    EXPOSE 80
    CMD ["apache2-foreground"]
    