#!/usr/bin/env bash
# Створення hosting/deploy-package/ — повна копія app/ для заливання на хостинг.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
"$ROOT/hosting/prepare-deploy.sh"

PACKAGE_DIR="${DEPLOY_PACKAGE_DIR:-$ROOT/hosting/deploy-package}"

rm -rf "$PACKAGE_DIR"
mkdir -p "$PACKAGE_DIR"

rsync -a \
  --exclude '.git' \
  --exclude 'node_modules' \
  --exclude 'tests' \
  --exclude '.env' \
  --exclude '.phpunit.cache' \
  --exclude 'storage/logs/*.log' \
  --exclude 'public/hot' \
  --exclude 'public/storage' \
  --exclude '.DS_Store' \
  "$ROOT/app/" "$PACKAGE_DIR/"

# Симлінк public/storage з локальної машини на хості не працює — створюється через storage:link на сервері.
rm -f "$PACKAGE_DIR/public/storage"

cp "$ROOT/hosting/env.production.template" "$PACKAGE_DIR/env.production.TEMPLATE"
cp "$ROOT/hosting/ZOOOGLE-UPLOAD-README.txt" "$PACKAGE_DIR/ZOOOGLE-UPLOAD-README.txt"

for release_note in "$ROOT"/hosting/DEPLOY-RELEASE-*.txt; do
  [[ -f "$release_note" ]] || continue
  cp "$release_note" "$PACKAGE_DIR/"
done

echo "Пакет готовий: $PACKAGE_DIR ($(du -sh "$PACKAGE_DIR" | cut -f1))"
echo "Архів (опційно): tar -czf hosting/deploy-package.tar.gz -C hosting $(basename "$PACKAGE_DIR")"
