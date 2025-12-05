# ─────────────────────────────────────────────────────────
# GLOBAL CARNIVORE COACHES — FINAL DOCKERFILE (2025)
# Persistent uploads + Correct PHP routing
# ─────────────────────────────────────────────────────────

FROM php:8.3-fpm

# Install nginx + GD for image processing
RUN apt-get update && apt-get install -y \
    nginx \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && rm -rf /var/lib/apt/lists/*

# Allow uploads up to 20MB
RUN echo "upload_max_filesize = 20M" > /usr/local/etc/php/conf.d/uploads.ini \
 && echo "post_max_size = 25M"     >> /usr/local/etc/php/conf.d/uploads.ini \
 && echo "memory_limit = 256M"     >> /usr/local/etc/php/conf.d/uploads.ini

# Copy application into place
COPY . /var/www/html
WORKDIR /var/www/html

# ───────────────────────────
# NGINX CONFIGURATION
# ───────────────────────────
RUN rm -f /etc/nginx/sites-enabled/default

COPY <<EOF /etc/nginx/conf.d/gcc.conf
server {
    listen 8080;
    server_name _;

    root /var/www/html;
    index index.php index.html;

    client_max_body_size 25M;

    # Try static files first, fallback to PHP
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # PHP handling
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
    }

    # Serve uploaded images directly from persistent disk
    location ^~ /uploads/ {
        alias /data/uploads/;
        autoindex off;
        add_header Cache-Control "public, max-age=31536000, immutable";
    }
}
EOF

# ───────────────────────────
# Persistent Upload Storage
# ───────────────────────────
RUN mkdir -p /data/uploads \
 && chmod -R 775 /data/uploads \
 && chown -R www-data:www-data /data/uploads

# Render uses port 8080
EXPOSE 8080

# ───────────────────────────
# Startup Command
# ───────────────────────────
CMD ln -sf /data/uploads /var/www/html/uploads \
 && php-fpm -D \
 && nginx -g 'daemon off;'
