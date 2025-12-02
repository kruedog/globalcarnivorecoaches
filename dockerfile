# -------------------------------
# Global Carnivore Coaches – Render Dockerfile (Final Version)
# Direct disk serving via Nginx alias — no symlinks needed
# -------------------------------

FROM php:8.2-fpm

# Install system dependencies & nginx
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Copy code
COPY . /var/www/html
WORKDIR /var/www/html

# Nginx config (serves PHP + static files + ALIAS for persistent disk uploads)
COPY <<EOF /etc/nginx/sites-available/default
server {
    listen 8080;
    index index.php index.html;
    server_name localhost;
    root /var/www/html;
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }
    # DIRECT SERVE UPLOADS FROM PERSISTENT DISK — NO SYMLINK NEEDED
    location /public/webapi/uploads/ {
        alias /opt/render/project/src/webapi/uploads/;
        autoindex off;
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files \$uri =404;
    }
}
EOF

# Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port Render expects
EXPOSE 8080

# Start PHP-FPM + Nginx
CMD service php8.2-fpm start && nginx -g 'daemon off;'