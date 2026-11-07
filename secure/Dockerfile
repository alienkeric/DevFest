FROM php:8.3-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod headers

RUN printf '%s\n' \
    '<Directory /var/www/html>' \
    '    AllowOverride All' \
    '</Directory>' \
    > /etc/apache2/conf-available/devfest.conf \
    && a2enconf devfest

COPY php.ini /usr/local/etc/php/conf.d/zz-devfest.ini
COPY src/ /var/www/html/

RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 775 {} \; \
    && find /var/www/html -type f -exec chmod 664 {} \;

EXPOSE 80
