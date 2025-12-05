# ─────────────────────────────────────────────────────────
# GLOBAL CARNIVORE COACHES — FINAL DOCKERFILE (2025)
# Persistent uploads at /data/uploads
# ─────────────────────────────────────────────────────────
FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    nginx \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && rm -rf /var/lib/apt/lists/*

# Allow larger uploads
RUN echo "upload_max_filesize = 20M" > /usr/local/etc/php/conf.d/uploads.ini \
 && echo "post_max_size = 25M"     >> /usr/local/etc/php/conf.d/uploads.ini \
 && echo "memory_limit = 256M"    >> /usr/local/etc/php/conf.d/uploads.ini

COPY . /var/www/html
WORKDIR /var/www/html

# Ensure uploads persist
RUN mkdir -p /data/uploads \
 && chmod -R 775 /data/uploads \
 && chown -R www-data:www-data /data/uploads

# Auto-symlink uploads to web root
CMD ln -sf /data/uploads /var/www/html/uploads \
 && php-fpm -D \
 && nginx -g 'daemon off;'

EXPOSE 8080
