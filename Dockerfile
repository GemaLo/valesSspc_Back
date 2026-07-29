FROM php:8.3-fpm

# 1. Instalar dependencias del sistema y solucionar libaio1t64
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    zip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    unzip \
    git \
    curl \
    libzip-dev \
    libaio-dev \
    && (apt-get install -y libaio1t64 || apt-get install -y libaio1) \
    && rm -rf /var/lib/apt/lists/*

# Crear symlink crítico para libaio
RUN ln -s /usr/lib/x86_64-linux-gnu/libaio.so.1t64 /usr/lib/x86_64-linux-gnu/libaio.so.1 || true

# Configurar Git dentro del contenedor para evitar el warning de ownership
RUN git config --global --add safe.directory /var/www

# 2. Configurar Instant Client usando tus zips locales
WORKDIR /opt/oracle
COPY instantclient-basiclite-linux.x64-21.6.0.0.0dbru.zip instantclient-basic.zip
COPY instantclient-sdk-linux.x64-21.6.0.0.0dbru.zip instantclient-sdk.zip

RUN unzip -o instantclient-basic.zip \
    && unzip -o instantclient-sdk.zip \
    && rm -f instantclient-basic.zip instantclient-sdk.zip \
    && mv instantclient_21_6 instantclient

# Variables de entorno e integración con el linker
ENV ORACLE_HOME=/opt/oracle/instantclient
ENV LD_LIBRARY_PATH=/opt/oracle/instantclient:/usr/lib/x86_64-linux-gnu
RUN echo /opt/oracle/instantclient > /etc/ld.so.conf.d/oracle-instantclient.conf && ldconfig

# 3. Compilar e instalar extensiones de PHP (Nota: para PHP 8.3 usamos oci8-3.3.0 o posterior)
RUN echo 'instantclient,/opt/oracle/instantclient' | pecl install oci8-3.3.0 \
    && docker-php-ext-configure pdo_oci --with-pdo-oci=instantclient,/opt/oracle/instantclient \
    && docker-php-ext-install pdo_oci zip pdo_mysql gd bcmath \
    && docker-php-ext-enable oci8

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer dump-autoload --ignore-platform-reqs

EXPOSE 9000

CMD ["php-fpm"]