ARG PHP_IMAGE=php:8-apache
FROM $PHP_IMAGE

ARG WWWDATA_UID=33
ARG WWWDATA_GID=33

COPY src/ /var/www/html/

#RUN sed -i 's/UMASK.*022/UMASK           007/g' /etc/login.defs

# drop to unprivileged user
USER www-data
