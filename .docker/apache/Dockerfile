FROM php:8.4.8-apache

# Network tools for debugging purposes
RUN apt-get update && \
    apt-get install -y iputils-ping net-tools traceroute curl dnsutils libicu-dev && \
    apt-get clean

# Mysql
RUN apt-get update && docker-php-ext-install pdo pdo_mysql intl
RUN apt-get install -y default-mysql-client

ENV TZ="UTC"

COPY ./.docker/apache/custom-php.ini /usr/local/etc/php/conf.d/custom-php.ini
COPY ./.docker/apache/.aws /.aws
COPY ./webapp /var/www

# Make sure the directories logs, tmp and all its subdirectories in your CakePHP installation are writable by the web server user
RUN chown -R www-data:www-data /var/www/tmp && chown -R www-data:www-data /var/www/logs

# Apache custom config
COPY ./.docker/apache/localhost.conf /etc/apache2/sites-available/localhost.conf
RUN a2dissite 000-default && a2ensite localhost && a2enmod rewrite