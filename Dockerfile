# Use the official PHP image with Apache as the base
FROM php:7.4-apache

# Set the working directory inside the container
WORKDIR /var/www/html

# Install system dependencies needed for Laravel
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    libonig-dev \
    libzip-dev \
    curl \
    # Remove unnecessary files to keep the image size small
    --no-install-recommends && rm -rf /var/lib/apt/lists/*

# Install necessary PHP extensions for a Laravel app
RUN docker-php-ext-install pdo pdo_mysql gd exif opcache
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install -j$(nproc) gd

# Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy the entire Laravel application into the container
COPY . .

# Run Composer to install PHP dependencies (without dev dependencies)
RUN composer install --no-dev --optimize-autoloader

# Give Apache ownership of the project directory for permissions
RUN chown -R www-data:www-data /var/www/html

# Configure Apache to use the public directory as the root
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Enable the Apache rewrite module
RUN a2enmod rewrite

# Expose port 80 and start Apache
EXPOSE 80

CMD ["apache2-foreground"]
