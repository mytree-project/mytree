#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=ops/_common.sh
source "${SCRIPT_DIR}/_common.sh"

require_docker
require_env_file

services=(app nginx postgres redis worker scheduler)

printf 'Compose services\n'
printf '%-12s %-14s %-12s\n' 'SERVICE' 'STATE' 'HEALTH'
printf '%-12s %-14s %-12s\n' '-------' '-----' '------'
for service in "${services[@]}"; do
    printf '%-12s %-14s %-12s\n' \
        "${service}" \
        "$(service_state "${service}")" \
        "$(service_health "${service}")"
done

printf '\nCompose detail\n'
compose ps

printf '\nApplication HTTP reachability\n'
if [[ "$(service_state nginx)" == 'running' ]] && \
   compose exec -T nginx wget -q -O /dev/null http://127.0.0.1/up; then
    printf 'Laravel /up through Nginx: reachable\n'
else
    printf 'Laravel /up through Nginx: unreachable\n'
fi

printf '\nLaravel environment\n'
if [[ "$(service_state app)" == 'running' ]]; then
    if ! compose exec -T app php artisan about --only=environment --no-ansi; then
        printf 'Laravel environment information is unavailable.\n'
    fi

    printf '\nFilament\n'
    if ! compose exec -T app composer show filament/filament --no-ansi 2>/dev/null | sed -n '1,4p'; then
        printf 'Filament package information is unavailable.\n'
    fi
else
    printf 'Application container is not running; Laravel/Filament information is unavailable.\n'
fi
