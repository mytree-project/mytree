#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=ops/_common.sh
source "${SCRIPT_DIR}/_common.sh"

DEFAULT_URL='https://szukajwarchiwach.gov.pl/skan/-/skan/cf34102284d1121630d7e065baff12133f77944f57015c27db5bb8f666cd9c38'
DEFAULT_OUTPUT='storage/app/private/szukajwarchiwach-poc'

usage() {
    cat <<'USAGE'
Usage:
  ./ops/szukajwarchiwach-browser-poc.sh [URL] [OUTPUT_DIR]

Runs a manual Playwright/Chromium compatibility probe against Szukaj w Archiwach.

Defaults:
  URL        The real /skan/-/skan/<token> URL used while investigating issue #169.
  OUTPUT_DIR storage/app/private/szukajwarchiwach-poc

The probe:
  1. starts an ephemeral headless Chromium BrowserContext,
  2. opens the portal first to establish normal browser session state,
  3. requests the target through BrowserContext.request, which shares that session,
  4. if necessary, navigates the browser to the target and retries once,
  5. stores an image on success or HTML/screenshots/diagnostics on failure.

It never prints cookie values and does not solve CAPTCHAs or add stealth/browser-disguise behavior.

Run ./ops/test.sh install first on a fresh checkout so Node dependencies and Chromium are present.
USAGE
}

if [[ "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
    usage
    exit 0
fi

target_url="${1:-${DEFAULT_URL}}"
output_dir="${2:-${DEFAULT_OUTPUT}}"

require_docker
require_env_file

if [[ ! -d "${PROJECT_ROOT}/node_modules/playwright" ]]; then
    printf 'Error: Playwright Node dependencies are missing. Run ./ops/test.sh install first.\n' >&2
    exit 1
fi

printf 'Running Szukaj w Archiwach browser-session PoC in headless Chromium...\n'
printf 'Target: %s\n' "${target_url}"
printf 'Output: %s\n' "${output_dir}"

compose run --rm --no-deps --user "${HOST_UID}:${HOST_GID}" app \
    node ops/poc/szukajwarchiwach-browser-session.mjs \
    "--url=${target_url}" \
    "--output=${output_dir}"
