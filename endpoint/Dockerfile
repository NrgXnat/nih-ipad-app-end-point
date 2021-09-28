ARG PHP_VERSION
FROM php:${PHP_VERSION}-apache
COPY src/ /var/www/html/

# drop to unprivileged user
USER www-data
