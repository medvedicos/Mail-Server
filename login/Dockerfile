FROM php:8.1-apache

# Install curl extension
RUN apt-get update && apt-get install -y libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

# Copy application
COPY index.php /var/www/html/index.php

# Set permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
