# ZOOGLE — фото товарів і лого на хостингу

## Швидке виправлення (SSH)

```bash
cd ~/domains/zoogle.in.ua/deploy-package

# 1. Прибрати «битий» симлінк з пакета (якщо є)
rm -f public/storage

# 2. Створити правильний на сервері
php artisan storage:link
ls -la public/storage

# 3. Перевірити лого в шапці
ls -la public/images/zoogle-logo-new.png

# 4. Чи є завантажені фото товарів
find storage/app/public -type f | head -10

# 5. Права
chmod -R u+rwX storage bootstrap/cache
php artisan config:clear && php artisan config:cache
```

## Document Root (обовʼязково)

У панелі хостингу **корінь сайту** має бути:

```
/home/ВАШ_ЮЗЕР/domains/zoogle.in.ua/deploy-package/public
```

Не `deploy-package/` і не `public_html` без файлів з `public/images/`.

## Перевірка в браузері

| URL | Очікування |
|-----|------------|
| `https://zoogle.in.ua/images/zoogle-logo-new.png` | відкривається лого |
| `https://zoogle.in.ua/favicon.png` | іконка вкладки |
| `https://zoogle.in.ua/storage/…` | фото товару (шлях з адмінки) |

Якщо **лого 404** — залийте `public/images/` з пакета або з локального `app/public/images/`.

Якщо **storage 404** — виконайте `php artisan storage:link` (див. вище).

Якщо **storage/link ок, але файлів немає** — у `storage/app/public` порожньо: завантажте фото в **адмінці** або скопіюйте стару теку `storage/app/public` з бекапу (не затирайте сесії без потреби).

## .env

```env
APP_URL=https://zoogle.in.ua
FORCE_URL_FROM_INCOMING_REQUEST=true
```

## Симлінки заборонені на хостингу

Якщо `php artisan storage:link` пише помилку — зверніться в підтримку BestHosting. Тимчасово можна скопіювати вміст:

```bash
mkdir -p public/storage
cp -a storage/app/public/. public/storage/
```

(після нових завантажень у адмінці знову копіюйте або увімкніть симлінки).

## Мобільне: лого «зникає»

На телефоні те саме зображення `images/zoogle-logo-new.png`. Якщо на десктопі лого є, а на мобільному ні — оновіть кеш браузера. Якщо ніде немає — файл не залитий або невірний Document Root.
