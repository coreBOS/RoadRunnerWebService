FROM ubuntu:20.04
LABEL Description="coreBOS linux-apache-php-roadrunner production container"

SHELL ["/bin/bash", "-c"]

# Use the default UTF-8 language.
ENV LANG C.UTF-8

ENV TZ=Europe/Rome
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

ARG BASICAUTHUSER
ARG BASICAUTHPASS

RUN apt-get update && apt-get -y install apt-utils software-properties-common && add-apt-repository ppa:ondrej/php && apt-get update && apt-get install -y apache2 curl mysql-client && apt-get install -y php7.4 && apt-get install -y libapache2-mod-php7.4 php7.4-curl php7.4-json php7.4-gd php7.4-xml php7.4-soap php7.4-mbstring php7.4-zip php7.4-mysql cron && apt-get clean

COPY config/php.ini /etc/php/7.4/apache2
COPY config/php.ini /etc/php/7.4/cli
COPY config/apache2.conf /etc/apache2
COPY config/nolistar.conf /etc/apache2/conf-enabled
COPY config/roadrunner /etc/init.d/

RUN apt-get install -y vim curl git wget
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY --chown=www-data:www-data corebos/ /var/www/html

RUN rm -rf /var/www/html/index.html

COPY config/coreboscron.sh /etc/cron.d/coreboscron
RUN chmod 0644 /etc/cron.d/coreboscron
RUN crontab /etc/cron.d/coreboscron

# COPY .htaccesscrm /var/www/html/.htaccess
# RUN htpasswd -cb /var/www/html/.htpasswd $BASICAUTHUSER $BASICAUTHPASS
# Configure a healthcheck to validate that everything is up&running
HEALTHCHECK --timeout=10s CMD curl --silent --fail http://127.0.0.1/ping.php

EXPOSE 80 443 8081

WORKDIR /var/www/html

COPY config/start.sh .
RUN chmod +x start.sh

CMD ["./start.sh"]
