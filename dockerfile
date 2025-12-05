# ───────────────────────────────────────────────
# Global Carnivore Coaches — Stable Dockerfile
# For Render + Nginx + PHP-FPM + Persistent Upload Disk
# ───────────────────────────────────────────────

FROM php:8.3-fpm

# Install nginx + image dependencies
RUN apt-get update && apt-get install -y \
    nginx \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && rm -rf /var/lib/apt/lists/*

# Increase upload limits
RUN echo "upload_max_filesize = 20M" > /usr/local/etc/php/conf.d/uploads.ini \
 && echo "post_max_size = 25M"     >> /usr/local/etc/php/conf.d/uploads.ini \
 && echo "memory_limit = 256M"     >> /usr/local/etc/php/conf.d/uploads.ini

# Copy application
WORKDIR /var/www/html
COPY . .

# Nginx Config (STATIC FILE SERVING + PHP)
RUN rm -f /etc/nginx/sites-enabled/default
COPY nginx.conf /etc/nginx/sites-enabled/default

# Ensure uploads + coaches.json areas exist & writable
RUN mkdir -p /data/uploads \
    && touch /data/uploads/coaches.json \
    && ln -sf /data/uploads /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html /data/uploads

# Expose Runtime Port
EXPOSE 8080

# Start services inside the same container
CMD php-fpm -D && nginx -g 'daemon off;'
