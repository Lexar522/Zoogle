# Оновлення сайту на хостингу

Для **BestHosting.ua** див. [BESTHOSTING-UA.md](BESTHOSTING-UA.md) (розміщення `public`, DirectAdmin).

Після першого деплою зміни вносяться у **локальному репозиторії** (`app/`), потім знову вивантажується код на сервер.

## Типові кроки оновлення

1. Локально: `git pull` (або ваш звичний workflow), переконайтесь що все працює.
2. Запустіть з кореню репозиторію (або вручну те саме):
   ```bash
   ./hosting/prepare-deploy.sh
   ```
3. Вивантажте на сервер **вміст каталогу `app/`** (через Git, FTP, rsync тощо), **не замінюючи**:
   - `app/.env` на сервері
   - `app/storage/` (логі, кеші, завантажені файли) — синхронізуйте обережно або лише код
   - `app/database/database.sqlite` — якщо використовуєте SQLite на продакшені

4. На сервері в каталозі `app/`:
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan migrate --force
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan filament:upgrade
   ```
   Якщо після оновлення зʼявились проблеми з конфігом:
   ```bash
   php artisan config:clear && php artisan config:cache
   ```

5. Якщо ви змінювали **JavaScript/CSS (Vite)**, перед оновленням на сервері має існувати **`public/build/`** з новою збіркою (скрипт `prepare-deploy.sh` робить `npm run build`).

## Що не чіпати на хості без потреби

- `.env` — лише нові ключі або зміни інтеграцій
- `storage/app/` — завантажені зображення товарів тощо
- Права: `storage` та `bootstrap/cache` мають бути доступні для запису веб-сервером (зазвичай `775` або за рекомендацією хостера)

## Filament /admin

Після оновлень пакетів інколи потрібно:
```bash
php artisan filament:upgrade
php artisan config:cache
```
