FROM php:7.0-cli-alpine
RUN apk update \
    && apk add zlib-dev \
    && docker-php-ext-install zip
RUN curl -sS https://getcomposer.org/installer | \
    php -- --install-dir=/usr/local/bin/ --filename=composer
COPY ./composer.json ./composer.lock /app/
RUN (cd /app && composer install --no-scripts --no-autoloader --no-dev --prefer-dist)
ADD ./ /app
RUN (cd /app && composer install --no-dev)
WORKDIR /app
RUN echo 'memory_limit = 512M' > /usr/local/etc/php/conf.d/app.ini
CMD sh -c "php -v"
