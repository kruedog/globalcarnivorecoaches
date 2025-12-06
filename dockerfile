# ────────────────────────────────────────────────
# Global Carnivore Coaches — Render Deployment
# nginx + PHP-FPM + Persistent Disk (/data/uploads)
# ────────────────────────────────────────────────

FROM php:8.3-fpm

# Install nginx + image processing libs (GD)
RUN apt-get update && apt-get install -y \
    nginx \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && rm -rf /var/lib/apt/lists/*

# PHP upload limits
RUN { \
    echo "upload_max_filesize = 50M"; \
    echo "post_max_size = 60M"; \
    echo "memory_limit = 512M"; \
    echo "max_file_uploads = 20"; \
} > /usr/local/etc/php/conf.d/uploads.ini

# App source code
WORKDIR /var/www/html
COPY . .

# Nginx: Replace default config with ours
RUN rm -f /etc/nginx/sites-enabled/default
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Ensure persistent uploads dir exists + is writable
# Render disk mount path: /data/uploads
RUN mkdir -p /data/uploads \
    && chmod -R 777 /data/uploads \
    && ln -sf /data/uploads /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html /data/uploads

# Expose for Render runtime
EXPOSE 8080

# Start PHP-FPM + nginx together
CMD php-fpm -D && nginx -g 'daemon off;'
