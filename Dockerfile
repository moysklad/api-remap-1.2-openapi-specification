# Локальная dev-среда для lint, bundle, generate, smoke, golden (docker compose).
# CI использует отдельные образы — см. gitlab/common/ci-images.yml и ci/*.Dockerfile.
FROM node:22-alpine

# PHP: расширения для PHPUnit — dom, xmlwriter, ctype и др.
RUN echo "https://dl-cdn.alpinelinux.org/alpine/v3.23/community" >> /etc/apk/repositories && \
  apk add --no-cache --repository=https://dl-cdn.alpinelinux.org/alpine/v3.23/community \
    php84 php84-curl php84-xml php84-xmlwriter php84-mbstring php84-phar php84-openssl php84-json php84-dom php84-tokenizer php84-ctype \
    composer && \
  ln -sf /usr/bin/php84 /usr/bin/php

# Python
RUN apk add --no-cache python3 py3-pip

# Java + Maven (для Java smoke/golden тестов)
RUN apk add --no-cache openjdk21-jre-headless maven
ENV JAVA_HOME=/usr/lib/jvm/java-21-openjdk

# Утилиты (make для Makefile, curl для healthcheck mock-сервера)
RUN apk add --no-cache git rsync make curl && \
  git config --global --add safe.directory /workspace

# Node: redocly, openapi-generator (mock-сервер запускается как sidecar через docker-compose)
RUN npm install -g npm@10 && \
  npm i -g @redocly/cli && \
  npm i -g @openapitools/openapi-generator-cli@2.12.1

WORKDIR /workspace

# Локально docker compose запускает контейнер под UID/GID пользователя хоста.
# Подготавливаем writable-директории для cache/volume paths, чтобы файлы в bind mount
# не создавались от root.
RUN mkdir -p /workspace/node_modules /tmp/.composer /tmp/.npm && \
  chown -R 1000:1000 /workspace /tmp/.composer /tmp/.npm
