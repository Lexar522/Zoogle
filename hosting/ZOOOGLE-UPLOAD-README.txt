ZOOGLE — архів для завантаження на хостинг
============================================

Ця папка — копія Laravel-проєкту після збірки:
  • vendor/ (Composer без dev-пакетів)
  • public/build/ (Vite, JS/CSS)

ВАЖЛИВО перед заливанням
------------------------
1. На сервері створіть файл .env у корені цієї папки. Орієнтир: файл env.production.TEMPLATE
   у цій самій папці (перейменуйте/скопіюйте як .env і допишіть дані бази та APP_URL).

2. НЕ публікуйте файл .env з реальними паролями.

3. На сервері в SSH у каталозі, де лежить artisan:

     php artisan key:generate
     php artisan migrate --force
     rm -f public/storage
     php artisan storage:link
     ls -la public/images/zoogle-logo-new.png
     php artisan config:cache
     php artisan route:cache
     php artisan view:cache
     php artisan filament:upgrade

4. Документ-root веб-сервера має вказувати на підкаталог public/ Laravel (не на корінь проєкту).

5. Фото товарів і лого: див. hosting/HOSTING-MEDIA-CHECKLIST-UK.md
   (після заливки обовʼязково rm -f public/storage && php artisan storage:link на сервері).

Підготувати архів на комп’ютері знову
-------------------------------------
У репозиторії проєкту (рядом з теками app/ та hosting/):

  ./hosting/make-deploy-package.sh

Результат: hosting/deploy-package/ (цю теку часто виключають з Git через розмір).

