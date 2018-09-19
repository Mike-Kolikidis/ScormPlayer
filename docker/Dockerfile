FROM ubuntu

MAINTAINER Antonis Hatzikonstantinou ahatzikonstantinou@gmail.com

# Ensure UTF-8
# Set the locale
#RUN apt-get clean && apt-get update && apt-get install -y locales
#RUN locale-gen en_US.UTF-8
ENV LANG en_US.UTF-8
ENV LC_ALL en_US.UTF-8

Run apt-get update

# noninteractive is necessary in order to stop dialogs from freezing the build of the container e.g. tzdata: set timezone
ENV DEBIAN_FRONTEND noninteractive

RUN apt-get install -y nginx php php-mbstring php-cli php-fpm php-intl php-xdebug php-curl
RUN echo "\ndaemon off;" >> /etc/nginx/nginx.conf
RUN sed -i -e "s/;\?daemonize\s*=\s*yes/daemonize = no/g" /etc/php/*/fpm/php-fpm.conf

# Nginx config
RUN rm /etc/nginx/sites-enabled/default
ADD docker/nginx.conf/scorm.conf /etc/nginx/sites-available/
ADD docker/nginx.conf/scorm-demo.conf /etc/nginx/sites-available/
RUN ln -s /etc/nginx/sites-available/scorm.conf /etc/nginx/sites-enabled/scorm.conf
RUN ln -s /etc/nginx/sites-available/scorm-demo.conf /etc/nginx/sites-enabled/scorm-demo.conf

# PHP config
RUN sed -i -e "s/;\?date.timezone\s*=\s*.*/date.timezone = Europe\/Athens/g" /etc/php/*/fpm/php.ini

# Define default command.
CMD service `basename -s .conf /etc/init/php*fpm*` start && nginx

# Expose ports.
EXPOSE 8080 8081

# The Proxy
COPY . /scormplayer

WORKDIR /scormplayer

RUN chown -R www-data:www-data /scormplayer