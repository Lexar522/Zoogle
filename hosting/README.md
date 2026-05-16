# Деплой на хостинг (ZOOGLE / Laravel)

Уся робоча копія коду знаходиться в каталозі **`../app`** (це корінь Laravel). Тут лише матеріали для заливання та оновлень на сервері.

| Файл | Призначення |
|------|-------------|
| [DEPLOY-UK.md](DEPLOY-UK.md) | Покрокова інструкція переносу та налаштування на хості |
| [**BESTHOSTING-UA.md**](BESTHOSTING-UA.md) | Особливості **BestHosting.ua** (DirectAdmin, `public_html`, PHP, cron) |
| [HOSTING-CHANGES.md](HOSTING-CHANGES.md) | Що міняти при оновленнях сайту після першого деплою |
| [env.production.template](env.production.template) | Шаблон `.env` для продакшену (скопіюйте як `.env` на сервері) |
| [prepare-deploy.sh](prepare-deploy.sh) | Локальний скрипт: збірка фронту + Composer без dev (перед упаковкою) |
| [make-deploy-package.sh](make-deploy-package.sh) | Збирає готовий каталог **`deploy-package/`** для FTP/архіву (≈повна копія `app/` без зайвого) |

**Швидкий старт:** прочитайте `DEPLOY-UK.md`, на сервері обовʼязково вкажіть `APP_URL`, `APP_KEY`, базу даних та зробіть `php artisan migrate --force`.
