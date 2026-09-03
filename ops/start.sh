#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=ops/_common.sh
source "${SCRIPT_DIR}/_common.sh"

require_docker
require_env_file

compose up -d
printf 'MyTree stack started. Run ./ops/status.sh for diagnostics.\n'
