# ─────────────────────────────────────────────────────────────
# GLOBAL CARNIVORE COACHES – RENDER DOCKERFILE (2025)
# Persistent Uploads + nginx + PHP-FPM
# ─────────────────────────────────────────────────────────────

FROM php:8.3-fpm

# Install nginx and image extensions
RUN apt-get update && apt-get install -y \
    nginx \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && rm -rf /var/lib/apt/lists/*

# PHP upload settings — avoid 413 errors
RUN echo "upload_max_filesize = 20M"   > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 25M"     >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M"     >> /usr/local/etc/php/conf.d/uploads.ini

# Copy app into container
COPY . /var/www/html
WORKDIR /var/www/html

# ────────────────────── NGINX CONFIG ──────────────────────
COPY <<EOF /etc/nginx/conf.d/default.conf
server {
    listen 8080;
    server_name _;

    root /var/www/html;
    index index.php index.html;

    client_max_body_size 25M;

    # Frontend routes fallback to index.php (SPA-friendly)
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # PHP backend API
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
    }

    # Uploaded coach images – served from Render persistent disk
    location ^~ /uploads/ {
        alias /data/uploads/;
        autoindex off;
        add_header Cache-Control "public, max-age=31536000, immutable";
    }
}
EOF

# ───────────────── Persistent uploads directory ─────────────────
# This is the **Render Persistent Disk mount location**
RUN mkdir -p /data/uploads \
    && chmod -R 775 /data/uploads

# Ensure all web files owned by nginx user
RUN chown -R www-data:www-data /var/www/html

# Expose Render port
EXPOSE 8080

# ───────────────── Startup ─────────────────
# Create symlink so `/uploads/*` served from persistent disk
# Must run on *every container boot*
CMD ln -sf /data/uploads /var/www/html/uploads \
    && php-fpm -D \
    && nginx -g 'daemon off;'
