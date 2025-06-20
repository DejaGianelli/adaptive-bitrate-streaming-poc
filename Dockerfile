FROM php:8.4.8-apache

# Mysql
RUN apt-get update && docker-php-ext-install pdo pdo_mysql
RUN apt-get install -y default-mysql-client

# Network tools for debugging purposes
RUN apt-get update && \
    apt-get install -y iputils-ping net-tools traceroute curl dnsutils && \
    apt-get clean

ENV TZ="UTC"

COPY ./ffmpeg /ffmpeg
ENV PATH="/ffmpeg:$PATH"

RUN mkdir /var/www/storage && chown -R www-data:www-data /var/www/storage && chmod -R 755 /var/www/storage

COPY ./custom-php.ini /usr/local/etc/php/conf.d/custom-php.ini
COPY ./src/ /var/www/html/
COPY ./storage/videos/ /storage/videos

# Change the ownership of the /uploads directory to the apache user and group www-data
RUN chown -R www-data:www-data /storage/videos && chmod -R 755 /storage/videos

# Create symlink to uploads folder
RUN ln -s /storage/videos /var/www/storage/videos

# Apache custom config
COPY ./localhost.conf /etc/apache2/sites-available/localhost.conf
RUN a2dissite 000-default && a2ensite localhost