# ───────────────────────────────────────────────
# Global Carnivore Coaches — Dockerfile (FIXED)
# Render + Nginx + PHP-FPM + Persistent Disk Support
# ───────────────────────────────────────────────

FROM php:8.3-fpm

# Install nginx + dependencies for image uploads
RUN apt-get update && apt-get install -y \
    nginx \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && rm -rf /var/lib/apt/lists/*

# --- PHP Upload configuration ---
RUN mkdir -p /tmp && chmod 1777 /tmp && \
    mkdir -p /usr/local/etc/php/conf.d && \
    { \
      echo "file_uploads = On"; \
      echo "upload_max_filesize = 20M"; \
      echo "post_max_size = 40M"; \
      echo "memory_limit = 256M"; \
      echo "max_file_uploads = 20"; \
      echo "upload_tmp_dir = /tmp"; \
    } > /usr/local/etc/php/conf.d/uploads.ini

# Set working directory
WORKDIR /var/www/html
COPY . .

# Nginx config
RUN rm -f /etc/nginx/sites-enabled/default
COPY nginx.conf /etc/nginx/sites-enabled/default

# Persistent upload disk mount
# /data/uploads provided by Render persistent disk
RUN mkdir -p /data/uploads \
    && chown -R www-data:www-data /data/uploads \
    && ln -sf /data/uploads /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html

# Expose Render port
EXPOSE 8080

# Start both services
CMD php-fpm -D && nginx -g 'daemon off;'
