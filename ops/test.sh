#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=ops/_common.sh
source "${SCRIPT_DIR}/_common.sh"

usage() {
    cat <<'USAGE'
Usage: ./ops/test.sh [all|install|style|static|tests|browser|browser-debug]

Commands:
  all      Install locked dependencies and run the standard non-browser quality gates (default)
  install  Build the app image and install Composer/Node dependencies from lock files
  style    Verify formatting with Laravel Pint without modifying files
  static   Run Larastan/PHPStan static analysis
  tests    Run the non-browser automated test suite through Pest/PHPUnit
  browser       Run Pest 4 browser tests through Playwright/Chromium (manual opt-in only)
  browser-debug Record and checkpoint the first Source Workspace validation browser test
USAGE
}

if (($# > 1)); then
    usage >&2
    exit 2
fi

command_name="${1:-all}"

run_browser_debug() {
    local video_dir="tests/Browser/Videos"
    local screenshot_dir="tests/Browser/Screenshots"

    mkdir -p "${video_dir}" "${screenshot_dir}"
    find "${video_dir}" -maxdepth 1 -type f -delete
    find "${screenshot_dir}" -maxdepth 1 -type f -name 'debug-*.png' -delete

    printf 'Running the first Source Workspace validation browser test in diagnostic mode...\n'
    printf 'Playwright video directory: %s\n' "${video_dir}"
    printf 'Checkpoint screenshot directory: %s\n' "${screenshot_dir}"
    printf 'Diagnostic watchdog: 120 seconds. Checkpoint output is written to stderr as each step completes.\n'

    browser_debug_run timeout --signal=INT --kill-after=15s 120s \
        vendor/bin/pest tests/Browser/SourceWorkspaceValidationTest.php \
        --filter='scopes Mention JSON validation styling' \
        --stop-on-failure || {
        status=$?

        if [[ ${status} -eq 124 || ${status} -eq 137 ]]; then
            printf 'Error: diagnostic browser test exceeded the 120 second watchdog.\n' >&2
        fi

        printf 'Diagnostic artifacts, if finalized, are under %s and %s.\n' "${video_dir}" "${screenshot_dir}" >&2

        return "${status}"
    }

    printf 'Diagnostic artifacts are under %s and %s.\n' "${video_dir}" "${screenshot_dir}"
}

case "${command_name}" in
    all|install|style|static|tests|browser|browser-debug)
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

browser_run() {
    compose run --rm --no-deps --user "${HOST_UID}:${HOST_GID}" \
        --env APP_ENV=testing \
        --env APP_DEBUG=false \
        --env APP_KEY="${TEST_APP_KEY}" \
        --env APP_LOCALE=pl \
        --env CACHE_STORE=array \
        --env DB_CONNECTION=sqlite \
        --env DB_DATABASE=:memory: \
        --env MAIL_MAILER=array \
        --env QUEUE_CONNECTION=sync \
        --env SESSION_DRIVER=cookie \
        app "$@"
}

browser_debug_run() {
    compose run --rm --no-deps --user "${HOST_UID}:${HOST_GID}" \
        --env APP_ENV=testing \
        --env APP_DEBUG=false \
        --env APP_KEY="${TEST_APP_KEY}" \
        --env APP_LOCALE=pl \
        --env CACHE_STORE=array \
        --env DB_CONNECTION=sqlite \
        --env DB_DATABASE=:memory: \
        --env MAIL_MAILER=array \
        --env QUEUE_CONNECTION=sync \
        --env SESSION_DRIVER=cookie \
        --env MYTREE_BROWSER_DEBUG=1 \
        app "$@"
}

install_dependencies() {
    printf 'Building the application image...\n'
    compose build app

    printf 'Installing locked Composer dependencies...\n'
    compose run --rm --no-deps --user "${HOST_UID}:${HOST_GID}" \
        --env COMPOSER_HOME=/tmp/composer app \
        composer install --no-interaction --prefer-dist --no-progress

    printf 'Installing locked Node dependencies for browser tests...\n'
    compose run --rm --no-deps --user "${HOST_UID}:${HOST_GID}" \
        --env NPM_CONFIG_CACHE=/tmp/npm-cache app \
        npm ci --no-audit --no-fund
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
    printf 'Running non-browser Pest/PHPUnit tests...\n'
    quality_run php artisan test tests/Architecture tests/Feature tests/Unit
}

run_browser_tests() {
    printf 'Running Pest browser tests with Playwright/Chromium...\n'
    printf 'Browser suite watchdog: 120 seconds; execution stops after the first failed test.\n'

    browser_run timeout --signal=TERM --kill-after=10s 120s \
        vendor/bin/pest tests/Browser --stop-on-failure || {
        status=$?

        if [[ ${status} -eq 124 || ${status} -eq 137 ]]; then
            printf 'Error: browser tests exceeded the 120 second watchdog. The suite is expected to finish well below this limit.\n' >&2
        fi

        return "${status}"
    }
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
    browser)
        run_browser_tests
        ;;
    browser-debug)
        run_browser_debug
        ;;
esac
