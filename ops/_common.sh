#!/usr/bin/env bash

set -Eeuo pipefail

OPS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${OPS_DIR}/.." && pwd)"
COMPOSE_FILE="${PROJECT_ROOT}/docker-compose.yml"
ENV_FILE="${PROJECT_ROOT}/.env"
ENV_EXAMPLE_FILE="${PROJECT_ROOT}/.env.example"

cd "${PROJECT_ROOT}"

export HOST_UID="${HOST_UID:-$(id -u)}"
export HOST_GID="${HOST_GID:-$(id -g)}"

compose() {
    docker compose --project-directory "${PROJECT_ROOT}" -f "${COMPOSE_FILE}" "$@"
}

require_command() {
    local command_name="$1"

    if ! command -v "${command_name}" >/dev/null 2>&1; then
        printf 'Error: required command "%s" was not found in PATH.\n' "${command_name}" >&2
        return 1
    fi
}

require_docker() {
    require_command docker

    if ! docker compose version >/dev/null 2>&1; then
        printf 'Error: Docker Compose v2 is required (the "docker compose" command is unavailable).\n' >&2
        return 1
    fi

    if ! docker info >/dev/null 2>&1; then
        cat >&2 <<'MESSAGE'
Error: the current user cannot access the Docker daemon without privilege escalation.
Configure Docker so this user can run `docker info` successfully without sudo, then retry.
MESSAGE
        return 1
    fi
}

require_env_file() {
    if [[ ! -f "${ENV_FILE}" ]]; then
        printf 'Error: %s does not exist. Run ./ops/setup.sh first.\n' "${ENV_FILE}" >&2
        return 1
    fi
}

service_container_id() {
    local service="$1"
    compose ps -q "${service}" 2>/dev/null | head -n 1
}

service_state() {
    local service="$1"
    local container_id

    container_id="$(service_container_id "${service}")"
    if [[ -z "${container_id}" ]]; then
        printf 'not-created'
        return 0
    fi

    docker inspect --format '{{.State.Status}}' "${container_id}" 2>/dev/null || printf 'unknown'
}

service_health() {
    local service="$1"
    local container_id

    container_id="$(service_container_id "${service}")"
    if [[ -z "${container_id}" ]]; then
        printf 'n/a'
        return 0
    fi

    docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}n/a{{end}}' "${container_id}" 2>/dev/null || printf 'unknown'
}
