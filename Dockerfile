FROM debian:jessie
MAINTAINER Yury Evtikhov <yury@evtikhov.info>
#
# This Dockerfile is intended only for test/development use.
# It will be a really BAD idea to use it for production or public services.
#

ENV DEBIAN_FRONTEND noninteractive

# Update and Upgrade system
RUN apt-get -y update && \
    apt-get -y install curl git-core php5-cli php5-curl php5-json php5-sqlite && \
    rm -rf /var/lib/apt/lists/*
RUN mkdir /app
RUN git clone --recursive https://github.com/tuxis-ie/nsedit.git /app/nsedit
RUN cp /app/nsedit/includes/config.inc.php-dist /app/nsedit/includes/config.inc.php
COPY docker-entrypoint.sh /app/nsedit/docker-entrypoint.sh
RUN chmod +x /app/nsedit/docker-entrypoint.sh

# Define working directory.
VOLUME /app/nsedit
WORKDIR /app/nsedit
EXPOSE 8080

CMD ["sh", "-c", "/app/nsedit/docker-entrypoint.sh"]

#
# Usage:
#    docker build -t nseditphp .
#    docker run -d --name pdns-nsedit -p 80:8080 nseditphp
#
