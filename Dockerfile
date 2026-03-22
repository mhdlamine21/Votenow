# VoteNow v4 - Dockerfile 
# Image PHP 8.1 + Apache avec extensions nécessaires
FROM php:8.1-apache

LABEL maintainer="Mouhamadou Lamine NIANG <mouhamedlniang@gmail.com>"
LABEL description="VoteNow v4 - Plateforme de vote universitaire"
LABEL version="4.0"

# Extensions PHP nécessaires 
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    zip \
    unzip \
    default-mysql-client \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mysqli \
        gd \
        fileinfo \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Activer mod_rewrite Apache 
RUN a2enmod rewrite headers

# Configuration Apache 
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        Options -Indexes +FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Configuration PHP 
RUN echo "upload_max_filesize = 20M\n\
post_max_size = 20M\n\
max_execution_time = 60\n\
memory_limit = 256M\n\
display_errors = Off\n\
log_errors = On\n\
error_log = /var/log/php_errors.log\n\
date.timezone = Africa/Dakar" > /usr/local/etc/php/conf.d/votenow.ini

# Copier le code source 
WORKDIR /var/www/html
COPY . .

# Permissions 
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/logs \
    && chmod 640 /var/www/html/.env 2>/dev/null || true

# Script de démarrage 
COPY scripts/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
