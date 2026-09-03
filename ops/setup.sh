#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=ops/_common.sh
source "${SCRIPT_DIR}/_common.sh"

require_docker

if [[ ! -f "${ENV_FILE}" ]]; then
    if [[ ! -f "${ENV_EXAMPLE_FILE}" ]]; then
        printf 'Error: %s is missing; cannot create local environment configuration.\n' "${ENV_EXAMPLE_FILE}" >&2
        exit 1
    fi

    cp "${ENV_EXAMPLE_FILE}" "${ENV_FILE}"
    sed -i "s/^HOST_UID=.*/HOST_UID=${HOST_UID}/" "${ENV_FILE}"
    sed -i "s/^HOST_GID=.*/HOST_GID=${HOST_GID}/" "${ENV_FILE}"
    printf 'Created %s from .env.example with host UID:GID %s:%s.\n' \
        "${ENV_FILE}" "${HOST_UID}" "${HOST_GID}"
else
    printf 'Keeping existing %s unchanged.\n' "${ENV_FILE}"
fi

printf 'Building application containers for host UID:GID %s:%s...\n' "${HOST_UID}" "${HOST_GID}"
compose build

printf 'Installing Composer dependencies in the application container...\n'
compose run --rm --no-deps --user "${HOST_UID}:${HOST_GID}" \
    --env COMPOSER_HOME=/tmp/composer app \
    composer install --no-interaction --prefer-dist

if grep -Eq '^APP_KEY=.+$' "${ENV_FILE}"; then
    printf 'APP_KEY is already configured; keeping the existing application key.\n'
else
    printf 'Generating Laravel application key...\n'
    compose run --rm --no-deps --user "${HOST_UID}:${HOST_GID}" app \
        php artisan key:generate --no-interaction
fi

printf 'Starting PostgreSQL and Redis...\n'
compose up -d postgres redis

printf 'Running database migrations...\n'
compose run --rm --user "${HOST_UID}:${HOST_GID}" app \
    php artisan migrate --force --no-interaction

printf 'Starting the complete MyTree stack...\n'
compose up -d

http_port="$(grep -E '^HTTP_PORT=' "${ENV_FILE}" | tail -n 1 | cut -d= -f2- || true)"
http_port="${http_port:-8080}"

printf '\nSetup complete.\n'
printf 'Application: http://localhost:%s/admin\n' "${http_port}"
printf 'Run ./ops/status.sh for service and application diagnostics.\n'
