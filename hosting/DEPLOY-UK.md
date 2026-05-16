# Деплой Laravel (ZOOGLE) на хостинг

Корінь проєкту на вашому компʼютері: **`app/`** (там `artisan`, `composer.json`, `public/`).

Якщо хостинг — **BestHosting.ua**, див. окремо **[BESTHOSTING-UA.md](BESTHOSTING-UA.md)**.

## Вимоги хостингу

- **PHP 8.3+** з розширеннями: `openssl`, `pdo`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl` (та ті, що потрібні для обраної БД, напр. `pdo_mysql`).
- **Composer** на сервері (або збираєте `vendor/` локально і вантажите разом із кодом).
- **MySQL / MariaDB** (рекомендовано для продакшену) або інша БД, підтримана Laravel.
- Документ-root веб-сервера має вказувати на **`app/public`**, а не на `app/`.

## 1. Підготовка локально (перед заливанням)

З кореня репозиторію (де лежать `app/` та `hosting/`):

```bash
chmod +x hosting/prepare-deploy.sh
./hosting/prepare-deploy.sh
```

Це збере фронт (`npm run build` → `public/build/`) і оновить залежності PHP без dev-пакетів.

## 2. Що вивантажити на сервер

Усі файли з **`app/`**, крім того, що не потрібно на продакшені (за бажанням не вантажте `tests/`, `node_modules/`).

**Обовʼязково** на сервері мають бути:

- `app/`, `bootstrap/`, `config/`, `database/`, `lang/`, `public/` (у т.ч. **`public/build/`** після збірки), `resources/`, `routes/`, `storage/`, `vendor/`, `artisan`, `composer.json`, `composer.lock`

## 3. Налаштування на сервері

1. Скопіюйте `hosting/env.production.template` у **`app/.env`** і заповніть:
   - `APP_URL` — повний URL сайту з `https://`
   - `DB_*` — дані бази з панелі хостингу
   - пошта, платежі тощо за потреби

2. Згенеруйте ключ додатку (один раз):
   ```bash
   cd /шлях/до/app
   php artisan key:generate
   ```

3. Права на запис (під свого користувача/групу веб-сервера):
   ```bash
   chmod -R u+rwX storage bootstrap/cache
   ```

4. Міграції:
   ```bash
   php artisan migrate --force
   ```

5. Кеш Laravel (продакшен):
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan filament:upgrade
   ```

6. Посилання для публічних файлів (якщо користуєтесь `storage` для файлів із `public`):
   ```bash
   php artisan storage:link
   ```

## 4. Документ-root і PHP

У панелі хостингу вкажіть **Document root** на:

```
.../шлях/до/app/public
```

Перевірте, що версія CLI PHP (`php -v`) відповідає тій, що обслуговує сайт (часто окремий селектор PHP у панелі).

## 5. Cron / черги (за потреби)

Якщо використовуєте `QUEUE_CONNECTION=database`, потрібен воркер або cron. У Laravel зазвичай:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

І окремий процес `queue:work` за документацією Laravel, якщо черги активні на продакені.

Уточніть у підтримки хостингу, чи доступні **supervisor**/довгі процеси.

## 6. HTTPS

Увімкніть сертифікат (Let's Encrypt) у панелі хостингу та встановіть `APP_URL=https://...`.

---

Після змінення коду дивіться **[HOSTING-CHANGES.md](HOSTING-CHANGES.md)**.
