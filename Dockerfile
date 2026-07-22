FROM php:8.2-fpm

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
    libaio-dev \
    libzip-dev \
    && (apt-get install -y libaio1t64 || apt-get install -y libaio1)

RUN ln -s /usr/lib/x86_64-linux-gnu/libaio.so.1t64 /usr/lib/x86_64-linux-gnu/libaio.so.1 || true

WORKDIR /opt/oracle
RUN curl -o instantclient-basic.zip https://download.oracle.com/otn_software/linux/instantclient/1923000/instantclient-basic-linux.x64-19.23.0.0.0dbru.zip \
    && curl -o instantclient-sdk.zip https://download.oracle.com/otn_software/linux/instantclient/1923000/instantclient-sdk-linux.x64-19.23.0.0.0dbru.zip \
    && unzip -o instantclient-basic.zip \
    && unzip -o instantclient-sdk.zip \
    && rm -f instantclient-basic.zip instantclient-sdk.zip \
    && mv instantclient_19_23 instantclient

ENV LD_LIBRARY_PATH=/opt/oracle/instantclient
ENV ORACLE_HOME=/opt/oracle/instantclient
RUN echo /opt/oracle/instantclient > /etc/ld.so.conf.d/oracle-instantclient.conf && ldconfig

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