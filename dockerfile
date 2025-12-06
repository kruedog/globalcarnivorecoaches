# ───────────────────────────────────────────────
# Global Carnivore Coaches — Stable Dockerfile
# Render + Nginx + PHP-FPM + Persistent Upload Disk
# ───────────────────────────────────────────────

FROM php:8.3-fpm

# Install nginx and image libraries
RUN apt-get update && apt-get install -y \
    nginx \
    libpng-dev libjpeg-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) gd \
 && rm -rf /var/lib/apt/lists/*

# PHP limits
RUN echo "upload_max_filesize = 50M" > /usr/local/etc/php/conf.d/uploads.ini \
 && echo "post_max_size = 55M"       >> /usr/local/etc/php/conf.d/uploads.ini \
 && echo "memory_limit = 512M"       >> /usr/local/etc/php/conf.d/uploads.ini

# Copy application
WORKDIR /var/www/html
COPY . .

# Nginx config
RUN rm -f /etc/nginx/sites-enabled/default
COPY nginx.conf /etc/nginx/sites-enabled/default

# Create persistent disk mount directory and uploads symlink
RUN mkdir -p /data/uploads \
 && ln -sf /data/uploads /var/www/html/uploads \
 && chown -R www-data:www-data /var/www/html /data/uploads

# 🔥 Force correct recursive permissions
RUN chmod -R 775 /var/www/html /data/uploads \
 && find /var/www/html -type d -exec chmod 775 {} \; \
 && find /var/www/html -type f -exec chmod 664 {} \; \
 && chmod -R 775 /data/uploads

# Copy entrypoint & ensure it's executable
COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

EXPOSE 8080

CMD ["/docker-entrypoint.sh"]
