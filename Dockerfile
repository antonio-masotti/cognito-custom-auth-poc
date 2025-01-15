FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nginx \
    supervisor

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath

# Install Xdebug
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Create necessary directories and set permissions
RUN mkdir -p /var/www/.aws && \
    mkdir -p /var/run/supervisor && \
    mkdir -p /var/log/supervisor && \
    mkdir -p /var/log/nginx && \
    chown -R www-data:www-data /var/www/.aws && \
    chown -R www-data:www-data /var/www

# Copy configuration files
COPY .docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY .docker/php/local.ini /usr/local/etc/php/conf.d/local.ini
COPY .docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
COPY .docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy application
COPY --chown=www-data:www-data . /var/www

# Modify php-fpm configuration to run as www-data
RUN echo "user = www-data" >> /usr/local/etc/php-fpm.d/www.conf && \
    echo "group = www-data" >> /usr/local/etc/php-fpm.d/www.conf

# Modify nginx configuration to run as www-data
RUN echo "user www-data;" >> /etc/nginx/nginx.conf

# Create wrapper script for supervisor
RUN echo '#!/bin/bash\n\
chmod -R 777 /var/log/supervisor /var/run/supervisor\n\
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf\n'\
> /usr/local/bin/supervisord-wrapper.sh && \
chmod +x /usr/local/bin/supervisord-wrapper.sh

# Expose port 80
EXPOSE 80

# Start supervisord with wrapper
CMD ["/usr/local/bin/supervisord-wrapper.sh"]
