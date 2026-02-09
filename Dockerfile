# Локальная среда для проверки SDK pipeline (lint, bundle, generate, smoke, golden)
# Поддерживаемые языки: php, python, java, javascript
# В CI используется образ docker.infra.lognex/docker-openapitools
FROM node:22-alpine

# PHP: из community (php83 в Alpine 3.21+)
RUN echo "https://dl-cdn.alpinelinux.org/alpine/v3.23/community" >> /etc/apk/repositories && \
  apk add --no-cache --repository=https://dl-cdn.alpinelinux.org/alpine/v3.23/community \
    php83 php83-curl php83-xml php83-mbstring php83-phar php83-openssl php83-json php83-dom php83-tokenizer \
    composer && \
  ln -sf /usr/bin/php83 /usr/bin/php

# Python
RUN apk add --no-cache python3 py3-pip

# Java (JRE достаточно для запуска тестов; JDK/Maven — при добавлении Java SDK)
RUN apk add --no-cache openjdk21-jre-headless
ENV JAVA_HOME=/usr/lib/jvm/java-21-openjdk

# Утилиты (make для запуска целей из Makefile)
RUN apk add --no-cache git rsync make

# Node: redocly, openapi-generator, prism
RUN npm install -g npm@10 && \
  npm i -g @redocly/cli && \
  npm i -g @openapitools/openapi-generator-cli@2.12.1 && \
  npm i -g @stoplight/prism-cli

WORKDIR /workspace
