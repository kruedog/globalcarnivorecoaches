# ─────────────────────────────────────────────────────────────
# GLOBAL CARNIVORE COACHES – FINAL RENDER DOCKERFILE (2025)
# Fixed paths for coaches.json + uploads
# ─────────────────────────────────────────────────────────────

FROM php:8.3-fpm

# Install nginx + GD extension
RUN apt-get update && apt-get install -y \
    nginx \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && rm -rf /var/lib/apt/lists/*

# Increase upload limits (no more 413 errors)
RUN echo "upload_max_filesize = 20M"   > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 25M"     >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M"    >> /usr/local/etc/php/conf.d/uploads.ini

# Copy entire app to container
COPY . /var/www/html
WORKDIR /var/www/html

# ────────────────────── NGINX CONFIG ──────────────────────
COPY --chown=www-data:www-data <<-EOF /etc/nginx/sites-available/default
server {
    listen 8080;
    index index.php index.html;
    server_name _;

    root /var/www/html;

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
        alias /var/www/html/webapi/uploads/;
        autoindex off;
        add_header Cache-Control "public, max-age=31536000, immutable";
    }
}
EOF

# ────────────────── Ensure webapi folder + files exist ──────────────────
RUN mkdir -p /var/www/html/webapi/uploads \
    && touch /var/www/html/webapi/coaches.json \
    && chown -R www-data:www-data /var/www/html/webapi

# ────────────────── General permissions ──────────────────
RUN chown -R www-data:www-data /var/www/html

# Expose Render port
EXPOSE 8080

# Start PHP-FPM + nginx
CMD php-fpm -D && nginx -g 'daemon off;'
