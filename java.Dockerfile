# Локальная среда для проверки java SDK pipeline (golden)
# В CI используется образ docker.infra.lognex/docker-openapitools
FROM maven:3.9-eclipse-temurin-11-alpine

# Утилиты (make для запуска целей из Makefile)
RUN apk add --no-cache make

WORKDIR /workspace

# Локально docker compose запускает контейнер под UID/GID пользователя хоста.
# Maven cache находится в /tmp, чтобы не писать в /root под non-root пользователем.
RUN mkdir -p /tmp/.m2/repository && \
  chown -R 1000:1000 /workspace /tmp/.m2