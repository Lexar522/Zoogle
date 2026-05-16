# Заливання на BestHosting.ua (DirectAdmin)

Офісна довідка хостера: **панель керування** (зазвичай **DirectAdmin**, порт **2222**); для типових сайтів файли кладуть у **`public_html`**. Для Laravel потрібно, щоб **корінь сайту вказував на каталог `public`** вашого проєкту (див. нижче). Загальні кроки: [DEPLOY-UK.md](DEPLOY-UK.md).

FAQ хостера (шляхи, SSL, тестовий піддомен): [Корисні запитання — технічні](https://besthosting.ua/ua/techfaq.php).

## PHP

У **DirectAdmin** оберіть **PHP 8.3** або максимально близьку доступну (**не нижче 8.3** для цього проєкту). Увімкніть розширення для Laravel: `openssl`, `pdo`, `pdo_mysql`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl` (назви в панелі можуть відрізнятися).

## Варіант A: зміна Document Root на `.../public`

1. Вивантажте **повний вміст** локальної теки `app/` на сервер у підкаталог домену (наприклад `domains/ваш-домен.ua/laravel/`), після локальної збірки:
   ```bash
   ./hosting/prepare-deploy.sh
   ```
   На сервері мають бути `artisan`, `vendor/`, `public/build/` тощо.

2. У DirectAdmin для домену задайте **Document Root** на каталог **`.../laravel/public`** (назва теки довільна). Якщо в панелі немає явного поля — зверніться в підтримку з проханням указати web root на `public` Laravel.

3. Створіть **`.env`** у корені Laravel (шаблон: [env.production.template](env.production.template)).

## Варіант B: код окремо від `public_html`

Приклад структури:

```
/home/ЮЗЕР/domains/ваш-домен.ua/
  site/              ← повний Laravel (як локальна тека app/)
  public_html/       ← лише файли з Laravel public/ (index.php, .htaccess, build/, …)
```

У **`public_html/index.php`** змініть шляхи на батьківську теку з кодом (два рядки):

```php
require __DIR__.'/../site/vendor/autoload.php';
$app = require_once __DIR__.'/../site/bootstrap/app.php';
```

Назву теки **`site`** замініть на вашу. Рядки з **`maintenance.php`** також мають брати файл з **`../site/storage/...`**, якщо залишаєте `index.php` копією з Laravel — перевірте всі `__DIR__.'/../`:

```php
if (file_exists($maintenance = __DIR__.'/../site/storage/framework/maintenance.php')) {
    require $maintenance;
}
```

## SSH і Composer

У FAQ BestHosting зазначено: **SSH** відкривають за зверненням на пошту хостера (умови — на їхньому сайті).

- Без SSH: готуйте збірку **локально** (`./hosting/prepare-deploy.sh`) і вантажте **`vendor/`** та **`public/build/`** по FTP.

## Cron для `schedule:run`

**Advanced Features** → **Cron Jobs**. Приклад (шляхи та `php` підставте свої):

```
* * * * * cd /home/ЮЗЕР/domains/ДОМЕН/site && php artisan schedule:run >> /dev/null 2>&1
```

Точний шлях до інтерпретатора PHP уточніть у підтримки або в SSH командою `which php`.

## Черги

На shared-хості довгі процеси обмежені. Уточніть у **BestHosting**, чи можна запускати `queue:work` через cron або чи є **Supervisor**.

## SSL та тест

Безкоштовний SSL і тестування до переїзду домену описані у [технічному FAQ BestHosting](https://besthosting.ua/ua/techfaq.php).
