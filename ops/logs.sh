#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=ops/_common.sh
source "${SCRIPT_DIR}/_common.sh"

usage() {
    cat <<'USAGE'
Usage: ./ops/logs.sh [--follow] [--tail N] [TARGET ...]

Targets:
  app        PHP-FPM / Laravel application container
  web        Nginx container
  worker     Laravel queue worker
  scheduler  Laravel scheduler
  all        All targets above (default)

Options:
  -f, --follow  Follow log output
  --tail N      Show the last N lines per service (default: 100)
  -h, --help    Show this help
USAGE
}

follow=false
tail_lines=100
targets=()

while (($# > 0)); do
    case "$1" in
        -f|--follow)
            follow=true
            shift
            ;;
        --tail)
            if (($# < 2)) || [[ ! "$2" =~ ^[0-9]+$ ]]; then
                printf 'Error: --tail requires a non-negative integer.\n' >&2
                exit 2
            fi
            tail_lines="$2"
            shift 2
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        app|web|worker|scheduler|all)
            targets+=("$1")
            shift
            ;;
        *)
            printf 'Error: unknown argument "%s".\n\n' "$1" >&2
            usage >&2
            exit 2
            ;;
    esac
done

require_docker
require_env_file

if ((${#targets[@]} == 0)); then
    targets=(all)
fi

services=()
add_service() {
    local service="$1"
    local existing

    for existing in "${services[@]:-}"; do
        [[ "${existing}" == "${service}" ]] && return 0
    done

    services+=("${service}")
}

for target in "${targets[@]}"; do
    case "${target}" in
        app)
            add_service app
            ;;
        web)
            add_service nginx
            ;;
        worker)
            add_service worker
            ;;
        scheduler)
            add_service scheduler
            ;;
        all)
            add_service app
            add_service nginx
            add_service worker
            add_service scheduler
            ;;
    esac
done

args=(logs --tail "${tail_lines}")
if [[ "${follow}" == true ]]; then
    args+=(-f)
fi
args+=("${services[@]}")

compose "${args[@]}"
