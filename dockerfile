# ─────────────────────────────────────────────────────────
# GLOBAL CARNIVORE COACHES — FINAL DOCKERFILE (2025)
# Persistent storage + correct routing
# ─────────────────────────────────────────────────────────
FROM php:8.3-fpm

# Install nginx + GD for images
RUN apt-get update && apt-get install -y \
    nginx \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && rm -rf /var/lib/apt/lists/*

# Upload limits
RUN echo "upload_max_filesize = 20M" > /usr/local/etc/php/conf.d/uploads.ini \
 && echo "post_max_size = 25M"     >> /usr/local/etc/php/conf.d/uploads.ini \
 && echo "memory_limit = 256M"     >> /usr/local/etc/php/conf.d/uploads.ini

COPY . /var/www/html
WORKDIR /var/www/html

# NGINX CONFIG — enable PHP routing + direct uploads serving
RUN rm -f /etc/nginx/sites-enabled/default
COPY <<EOF /etc/nginx/conf.d/gcc.conf
server {
    listen 8080;
    server_name _;

    root /var/www/html;
    index index.php index.html;

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

    location ^~ /uploads/ {
        alias /data/uploads/;
        autoindex off;
        add_header Cache-Control "public, max-age=31536000, immutable";
    }
}
EOF

# Persistent disks
RUN mkdir -p /data/uploads \
 && mkdir -p /data \
 && chmod -R 775 /data \
 && chown -R www-data:www-data /data

# Render port
EXPOSE 8080

# Create symlinks at boot
CMD ln -sf /data/uploads /var/www/html/uploads \
 && ln -sf /data/coaches.json /var/www/html/webapi/coaches.json \
 && php-fpm -D \
 && nginx -g 'daemon off;'
