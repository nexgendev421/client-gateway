#!/usr/bin/env bash
set -Eeuo pipefail

APP_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-main}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
MAINTENANCE_ENABLED=0

fail() {
    printf 'Deployment stopped: %s\n' "$1" >&2
    exit 1
}

restore_service() {
    if [[ "$MAINTENANCE_ENABLED" -eq 1 ]]; then
        "$PHP_BIN" artisan up --no-interaction || printf 'Warning: unable to leave maintenance mode; run php artisan up manually.\n' >&2
    fi
}

trap restore_service EXIT
trap 'fail "command failed on line ${LINENO}"' ERR

cd "$APP_ROOT"

[[ -f artisan && -f composer.json && -d .git ]] || fail 'run this script from a Git checkout of SiyazPOS Router.'
[[ -f .env ]] || fail 'the production .env file is missing; refusing to create or replace it.'
grep -Eq '^APP_KEY=(base64:)?[^[:space:]]+' .env || fail 'APP_KEY is empty in .env.'
[[ "$DEPLOY_BRANCH" =~ ^[A-Za-z0-9._/-]+$ ]] || fail 'DEPLOY_BRANCH contains unsupported characters.'

CURRENT_BRANCH="$(git branch --show-current)"
[[ "$CURRENT_BRANCH" == "$DEPLOY_BRANCH" ]] || fail "checked out branch '$CURRENT_BRANCH'; expected '$DEPLOY_BRANCH'."
git remote get-url origin >/dev/null 2>&1 || fail 'Git remote origin is not configured.'
[[ -z "$(git status --porcelain)" ]] || fail 'working tree has changes; preserve and review them before deploying.'

printf 'Fetching origin/%s...\n' "$DEPLOY_BRANCH"
git fetch --prune origin "$DEPLOY_BRANCH"

LOCAL_COMMIT="$(git rev-parse HEAD)"
REMOTE_COMMIT="$(git rev-parse "origin/$DEPLOY_BRANCH")"
BASE_COMMIT="$(git merge-base HEAD "origin/$DEPLOY_BRANCH")"
[[ "$BASE_COMMIT" == "$LOCAL_COMMIT" || "$LOCAL_COMMIT" == "$REMOTE_COMMIT" ]] || fail 'local and remote history diverged; refusing to merge or overwrite.'

if [[ "$LOCAL_COMMIT" != "$REMOTE_COMMIT" ]]; then
    git merge --ff-only "origin/$DEPLOY_BRANCH"
fi

printf 'Enabling maintenance mode...\n'
"$PHP_BIN" artisan down --retry=60 --refresh=15 --no-interaction
MAINTENANCE_ENABLED=1

printf 'Installing production Composer dependencies...\n'
"$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --optimize-autoloader

printf 'Building frontend assets...\n'
"$NPM_BIN" ci --no-audit --no-fund
"$NPM_BIN" run build

printf 'Running database migrations...\n'
"$PHP_BIN" artisan migrate --force --no-interaction

printf 'Rebuilding Laravel caches...\n'
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

if "$PHP_BIN" artisan queue:restart --no-interaction >/dev/null 2>&1; then
    printf 'Requested queue worker restart.\n'
fi

"$PHP_BIN" artisan up --no-interaction
MAINTENANCE_ENABLED=0

printf 'Deployment complete at %s (%s).\n' "$REMOTE_COMMIT" "$DEPLOY_BRANCH"
