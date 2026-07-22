#!/usr/bin/env bash
set -euo pipefail

base_reference="${BASE_SHA:-HEAD^}"

if [[ "$base_reference" =~ ^0+$ ]]; then
  base_reference='HEAD^'
fi

if git rev-parse --quiet --verify "${base_reference}^{commit}" >/dev/null 2>&1; then
  diff_reference="${base_reference}...HEAD"
elif git rev-parse --quiet --verify 'HEAD^' >/dev/null 2>&1; then
  diff_reference='HEAD^...HEAD'
else
  diff_reference=''
fi

if [[ -n "$diff_reference" ]] && git diff --quiet --diff-filter=AM "$diff_reference" -- src; then
  echo 'Changed-source coverage skipped: no changed PHP source files.'
  exec php vendor/bin/phpunit
fi

mkdir -p build
php vendor/bin/phpunit --coverage-clover build/coverage.xml
php scripts/check-changed-coverage.php build/coverage.xml "$base_reference" 70
