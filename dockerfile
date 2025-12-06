# ───────────────────────────────────────────────
# Global Carnivore Coaches — PRODUCTION Dockerfile
# Works with Render Persistent Disk (/data/uploads)
# nginx + PHP-FPM 8.3 + secure writable uploads
# ───────────────────────────────────────────────

FROM php:8.3-fpm

# Install nginx + GD image dependencies
RUN apt-get update && apt-get install -y \
    nginx \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && rm -rf /var/lib/apt/lists/*

# PHP upload limits for images
RUN { \
    echo "upload_max_filesize = 100M"; \
    echo "post_max_size = 120M"; \
    echo "memory_limit = 512M"; \
    echo "max_file_uploads = 20"; \
} > /usr/local/etc/php/conf.d/uploads.ini

# App source
WORKDIR /var/www/html
COPY . .

# Nginx configuration
RUN rm -f /etc/nginx/sites-enabled/default \
    && rm -f /etc/nginx/conf.d/default.conf
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Persistent disk mount path on Render: /data/uploads
# Ensure writable by PHP (www-data)
RUN mkdir -p /data/uploads \
    && rm -rf /var/www/html/uploads \
    && ln -sf /data/uploads /var/www/html/uploads \
    && chown -R www-data:www-data /data/uploads /var/www/html \
    && chmod -R 775 /data/uploads \
    && find /data/uploads -type d -exec chmod 775 {} \; \
    && find /data/uploads -type f -exec chmod 664 {} \;

# Expose service port for Render
EXPOSE 8080

# Start PHP-FPM + nginx together
CMD php-fpm -D && nginx -g 'daemon off;'
