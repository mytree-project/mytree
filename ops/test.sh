#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=ops/_common.sh
source "${SCRIPT_DIR}/_common.sh"

usage() {
    cat <<'USAGE'
Usage: ./ops/test.sh [all|install|style|static|tests]

Commands:
  all      Install locked dependencies and run every quality gate (default)
  install  Build the app image and install Composer dependencies from composer.lock
  style    Verify formatting with Laravel Pint without modifying files
  static   Run Larastan/PHPStan static analysis
  tests    Run the PHPUnit test suite
USAGE
}

if (($# > 1)); then
    usage >&2
    exit 2
fi

command_name="${1:-all}"

case "${command_name}" in
    all|install|style|static|tests)
        ;;
    -h|--help)
        usage
        exit 0
        ;;
    *)
        printf 'Error: unknown quality command "%s".\n\n' "${command_name}" >&2
        usage >&2
        exit 2
        ;;
esac

require_docker
require_env_file

TEST_APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='

quality_run() {
    compose run --rm --no-deps --user "${HOST_UID}:${HOST_GID}" \
        --env APP_ENV=testing \
        --env APP_DEBUG=false \
        --env APP_KEY="${TEST_APP_KEY}" \
        --env CACHE_STORE=array \
        --env DB_CONNECTION=sqlite \
        --env DB_DATABASE=:memory: \
        --env MAIL_MAILER=array \
        --env QUEUE_CONNECTION=sync \
        --env SESSION_DRIVER=array \
        app "$@"
}

install_dependencies() {
    printf 'Building the application image...\n'
    compose build app

    printf 'Installing locked Composer dependencies...\n'
    compose run --rm --no-deps --user "${HOST_UID}:${HOST_GID}" \
        --env COMPOSER_HOME=/tmp/composer app \
        composer install --no-interaction --prefer-dist --no-progress
}

run_style() {
    printf 'Verifying code style with Laravel Pint...\n'
    quality_run vendor/bin/pint --test
}

run_static_analysis() {
    printf 'Running Larastan/PHPStan...\n'
    quality_run vendor/bin/phpstan analyse --no-progress --memory-limit=1G
}

run_tests() {
    printf 'Running PHPUnit...\n'
    quality_run php artisan test
}

case "${command_name}" in
    all)
        install_dependencies
        run_style
        run_static_analysis
        run_tests
        ;;
    install)
        install_dependencies
        ;;
    style)
        run_style
        ;;
    static)
        run_static_analysis
        ;;
    tests)
        run_tests
        ;;
esac
