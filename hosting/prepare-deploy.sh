#!/usr/bin/env bash
# Запускайте з кореня репозиторію (поруч з каталогом app/).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
APP="$ROOT/app"

if [[ ! -f "$APP/artisan" ]]; then
  echo "Помилка: не знайдено $APP/artisan. Скрипт має лежати у hosting/ поруч з app/."
  exit 1
fi

cd "$APP"

echo "==> Composer (без dev, оптимізація автозавантажувача)"
composer install --no-dev --optimize-autoloader

if command -v npm >/dev/null 2>&1; then
  if [[ -f package-lock.json ]]; then
    echo "==> npm ci"
    npm ci
  else
    echo "==> npm install"
    npm install
  fi
  echo "==> npm run build (public/build/)"
  npm run build
else
  echo "!!! npm не знайдено — пропущено збірку Vite. Виконайте на машині з Node: cd app && npm ci && npm run build"
fi

echo ""
echo "Готово. Вивантажуйте на сервер вміст каталогу: $APP"
echo "Див. hosting/DEPLOY-UK.md"
