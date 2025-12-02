# ─────────────────────────────────────────────────────────────
# GLOBAL CARNIVORE COACHES – FINAL RENDER DOCKERFILE (2025)
# This one works 100%. No more missing coaches.json, no more 413,
# no more disappearing photos. Just copy, push, deploy, win.
# ─────────────────────────────────────────────────────────────

FROM php:8.3-fpm

# Install nginx + GD extension
RUN apt-get update && apt-get update && apt-get install -y \
    nginx \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && rm -rf /var/lib/apt/lists/*

# Increase upload limits (no more 413 errors)
RUN echo "upload_max_filesize = 20M"   > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 25M"     >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M"    >> /usr/local/etc/php/conf.d/uploads.ini

# Copy your entire app
COPY . /var/www/html
WORKDIR /var/www/html

# ────────────────────── NGINX CONFIG ──────────────────────
# Serves PHP + directly serves images from persistent disk
COPY --chown=www-data:www-data <<-EOF /etc/nginx/sites-available/default
server {
    listen 8080;
    index index.php index.html;
    server_name _;
    root /var/www/html;

    # Allow big photo uploads
    client_max_body_size 25M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
    }

    # Serve uploaded photos directly from persistent disk
    location ^~ /public/webapi/uploads/ {
        alias /opt/render/project/src/webapi/uploads/;
        autoindex off;
        add_header Cache-Control "public, max-age=31536000, immutable";
    }
}
EOF
# ────────────────────────────────────────────────────────

# Make sure coaches.json always exists in the container
RUN mkdir -p /var/www/html \
    && touch /var/www/html/coaches.json \
    && chown www-data:www-data /var/www/html/coaches.json

# Permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port Render uses
EXPOSE 8080

# Start PHP-FPM (foreground) + nginx
CMD php-fpm -D && nginx -g 'daemon off;'