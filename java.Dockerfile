# Локальная среда для проверки java SDK pipeline (lint, bundle, generate, smoke, golden)
# В CI используется образ docker.infra.lognex/docker-openapitools
FROM maven:3.9-eclipse-temurin-11-alpine

# Утилиты (make для запуска целей из Makefile)
RUN apk add --no-cache make

WORKDIR /workspace