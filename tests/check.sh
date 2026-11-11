#!/bin/sh
# Shared checks for native development, Docker, and GitHub Actions.
set -eu
cd "$(dirname "$0")/.."

php --version
composer validate --no-check-publish
find src demo tests -type f -name '*.php' -print0 | xargs -0 -n 1 php -l
printf 'PHP syntax checks passed.\n'
php -d error_reporting=E_ALL -d display_errors=1 tests/run.php
