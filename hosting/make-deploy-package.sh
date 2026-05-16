#!/usr/bin/env bash
# Створення hosting/deploy-package/ — повна копія app/ для заливання на хостинг.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
"$ROOT/hosting/prepare-deploy.sh"

rm -rf "$ROOT/hosting/deploy-package"
mkdir -p "$ROOT/hosting/deploy-package"

rsync -a \
  --exclude '.git' \
  --exclude 'node_modules' \
  --exclude 'tests' \
  --exclude '.env' \
  --exclude '.phpunit.cache' \
  --exclude 'storage/logs/*.log' \
  --exclude 'public/hot' \
  --exclude '.DS_Store' \
  "$ROOT/app/" "$ROOT/hosting/deploy-package/"

cp "$ROOT/hosting/env.production.template" "$ROOT/hosting/deploy-package/env.production.TEMPLATE"
cp "$ROOT/hosting/ZOOOGLE-UPLOAD-README.txt" "$ROOT/hosting/deploy-package/ZOOOGLE-UPLOAD-README.txt"

echo "Пакет готовий: $ROOT/hosting/deploy-package ($(du -sh "$ROOT/hosting/deploy-package" | cut -f1))"
