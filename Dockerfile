# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Webware Log development environment image
#
# Builds a self-contained, interactive PHP development environment carrying
# the Webware toolchain: PHP CLI, Composer, Mago, and Xdebug. Developers work
# inside this container — either through the VS Code Dev Container (backed by
# compose.yml) or by `docker compose up -d` + `docker compose exec tooling`.
# The host machine (Windows, WSL, Linux, or macOS) never needs a native PHP
# toolchain.
#
# Webware Log's integration tests exercise the MySQL-backed PhpDbHandler, so
# this image additionally installs the `pdo_mysql` (and `mysqli`) extensions
# on top of the base Webware toolchain image.
#
# All files in this repository are committed with LF line endings (see
# .gitattributes), which keeps the container and host line endings consistent.
# ---------------------------------------------------------------------------

# PHP version to base the image on (latest 8.4 patch release). Keep in sync
# with the package's supported PHP versions and `config.platform.php`.
ARG PHP_VERSION=8.4.24

# Composer version (copied from the official composer image at build time).
ARG COMPOSER_VERSION=2

# Named stage so Composer can be copied from it (--from does not support
# inline variable expansion).
FROM composer:${COMPOSER_VERSION} AS composer

FROM php:${PHP_VERSION}-cli

# ---------------------------------------------------------------------------
# System packages + PHP extensions
# ---------------------------------------------------------------------------
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        libicu-dev \
        libzip-dev \
        unzip \
    ; \
    docker-php-ext-install -j"$(nproc)" \
        intl \
        pcntl \
        zip \
        pdo_mysql \
        mysqli \
    ; \
    pecl install pcov \
    && docker-php-ext-enable pcov \
    ; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*

# ---------------------------------------------------------------------------
# Xdebug (step debugging)
# ---------------------------------------------------------------------------
# Installed but disabled by default (xdebug.mode=off) so it does not slow down
# normal Composer/PHPUnit/Mago runs. Enable it with XDEBUG_MODE=debug (see
# compose.yml) and connect the IDE to host.docker.internal.
RUN set -eux; \
    pecl install xdebug \
    && docker-php-ext-enable xdebug \
    ; \
    { \
        echo 'xdebug.mode=off'; \
        echo 'xdebug.start_with_request=yes'; \
        echo 'xdebug.client_host=host.docker.internal'; \
    } >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# ---------------------------------------------------------------------------
# Composer
# ---------------------------------------------------------------------------
COPY --from=composer /usr/bin/composer /usr/bin/composer

# ---------------------------------------------------------------------------
# Mago (statically linked Rust binary).
#
# The version is NOT pinned in this file. It is read from the `version =` pin in
# webware/webware-tools/mago.toml, resolved at the exact revision this
# repository's composer.lock requires -- so the tool inside the container always
# matches the pin the package's own config (which `extends` the centre
# mago.toml) enforces. That pin has exactly one home: webware/webware-tools.
#
# The release asset is selected for the build target architecture so the same
# Dockerfile works on x86_64 and arm64 hosts.
# ---------------------------------------------------------------------------
COPY composer.lock /tmp/composer.lock
ARG TARGETARCH
RUN set -eux; \
    case "${TARGETARCH}" in \
        amd64) MAGO_TRIPLE="x86_64-unknown-linux-gnu" ;; \
        arm64) MAGO_TRIPLE="aarch64-unknown-linux-gnu" ;; \
        *) echo "unsupported TARGETARCH: ${TARGETARCH}" >&2; exit 1 ;; \
    esac; \
    WEBWARE_TOOLS_REV="$(php -r '$lock = json_decode(file_get_contents("/tmp/composer.lock"), true, 512, JSON_THROW_ON_ERROR); foreach (array_merge($lock["packages"] ?? [], $lock["packages-dev"] ?? []) as $package) { if (($package["name"] ?? "") === "webware/webware-tools") { echo $package["source"]["reference"] ?? ""; break; } }')"; \
    if [ -z "${WEBWARE_TOOLS_REV}" ]; then \
        echo "composer.lock does not pin webware/webware-tools" >&2; \
        exit 1; \
    fi; \
    curl -fsSL \
        "https://raw.githubusercontent.com/webinertia/webware-tools/${WEBWARE_TOOLS_REV}/mago.toml" \
        -o /tmp/webware-tools-mago.toml; \
    MAGO_VERSION="$(sed -nE 's/^[[:space:]]*version[[:space:]]*=[[:space:]]*"([0-9]+\.[0-9]+\.[0-9]+)".*/\1/p' /tmp/webware-tools-mago.toml | head -n1)"; \
    if [ -z "${MAGO_VERSION}" ]; then \
        echo "no version pin in webware-tools/mago.toml at ${WEBWARE_TOOLS_REV}" >&2; \
        exit 1; \
    fi; \
    curl -fsSL \
        "https://github.com/carthage-software/mago/releases/download/${MAGO_VERSION}/mago-${MAGO_VERSION}-${MAGO_TRIPLE}.tar.gz" \
        -o /tmp/mago.tar.gz; \
    tar -xzf /tmp/mago.tar.gz -C /tmp; \
    install -m 0755 "/tmp/mago-${MAGO_VERSION}-${MAGO_TRIPLE}/mago" /usr/local/bin/mago; \
    rm -rf /tmp/mago.tar.gz "/tmp/mago-${MAGO_VERSION}-${MAGO_TRIPLE}" /tmp/composer.lock /tmp/webware-tools-mago.toml; \
    mago --version

WORKDIR /app
