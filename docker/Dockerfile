FROM php:8.3-cli-bookworm

# Set Debian mirrors (Liara - better for Iran)

# # Replace Debian mirrors with IUT mirror (Iran – stable)
# RUN printf "deb http://mirror-linux.runflare.com/debian bookworm main\n\
# deb http://mirror-linux.runflare.com/debian-security bookworm-security main\n\
# deb http://mirror-linux.runflare.com/debian-updates bookworm-updates main\n" \
# > /etc/apt/sources.list


# Install system packages + PHP extensions
RUN apt-get update && apt-get install -y \
    unzip \
    supervisor \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql gd zip pcntl sockets \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Work directory
WORKDIR /var/www

# Copy project files
COPY . .

# Composer install
RUN composer install --no-dev --optimize-autoloader

# Supervisor confrig

COPY ./docker/supervisor.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 8000
EXPOSE 8081

# Start supervisor
CMD ["/usr/bin/supervisord", "-n"]
