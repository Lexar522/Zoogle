# Zoogle: огляд швидкодії (версія 1.1.48)

Дата: 2026-11-05. Це лише огляд, код не змінювався.

## Як міряли

- **Локально.** Тимчасовий скрипт піднімав застосунок на *копії* `database/database.sqlite` (кеш і сесії в пам'яті, лог вимкнено) і рахував запити через `QueryExecuted`. Скрипт і копії бази лежали поза проєктом, у робочій теці сесії. У проєкті нічого не створено й не змінено.
- **Дві бази.** Спершу дані як є (4 товари, 1 комплект). Потім копія, де я розмножив товар `test` до 204 товарів по 3 варіанти, щоб побачити, як запити ростуть разом із каталогом.
- **Продакшен.** Кілька звичайних GET-запитів `curl` до zoogle.in.ua, щоб подивитися TTFB і заголовки.

### Локальні заміри (SQLite, без мережі до БД)

| Сторінка | Запитів (реальні дані) | Запитів (204 товари) | HTML |
|---|---|---|---|
| `/` | 136 | 172 | 397 КБ |
| `/catalog` (без фільтра) | 33 | 33 | 326 КБ |
| `/catalog?category=1` (24 картки) | 119 | **1432** | 475 КБ |
| `/catalog?category=1&per_page=48` | — | **2607** | 593 КБ |
| `/catalog?q=test` | — | **1650** | 464 КБ |
| `/catalog?bundles=1` | 80 | — | 346 КБ |
| сторінка товару `/catalog/test` | 136 | 225 | 378 КБ |
| комплект `/bundles/komplekt1` | 76 | 130 | 235 КБ |

На категорії з 24 картками приблизно **1200 із 1432 запитів** — це перевірки схеми (`Schema::hasTable` / `hasColumn`). Решта — це N+1 у картці товару, **близько 9 запитів на картку**.

### Продакшен (zoogle.in.ua, по 2 заміри)

| Сторінка | TTFB | Передано (gzip) |
|---|---|---|
| `/` | 0.41–0.50 с | 69 КБ |
| `/catalog` | 0.33–0.38 с | 57 КБ |
| `/catalog?category=5` | **0.65–0.67 с** | 66 КБ |
| `/catalog?on_sale=1` | 0.51 с | 61 КБ |
| сторінка товару | 0.43–0.45 с | 65 КБ |
| `/catalog?bundles=1` | 0.48–0.52 с | 61 КБ |

Сервер nginx з PHP 8.3, gzip увімкнено. Статика `/build/assets/*` і `/storage/*.webp` приходить **без `Cache-Control` і `Expires`**, є лише ETag і Last-Modified.

На MySQL кожен `Schema::hasTable` — це окремий запит до `information_schema`. На спільному хостингу він помітно дорожчий, ніж у SQLite. Тому на великій категорії проблеми з п. 1–2 дадуть секунди, а не мілісекунди.

---

## Знахідки (від найбільшого ефекту до найменшого)

### 1. `Schema::hasTable()` при кожному `getTable()` моделей Product і ProductVariant

- **Де:**
  - `app/Models/Product.php:27-29` і `app/Models/ProductVariant.php:19-21` викликають `CatalogProductsTable::name()` і `CatalogProductVariantsTable::name()`.
  - Самі ці методи: `app/Support/CatalogProductsTable.php:14`, `app/Support/CatalogProductVariantsTable.php:11`.
  - Зв'язки `Product::variants()` (`Product.php:112`), `Product::orderItems()` (`:102`) і `ProductVariant::product()` (`ProductVariant.php:63`) щоразу роблять `Schema::hasColumn`.
- **Що не так.** Eloquent викликає `getTable()` десятки разів на кожну модель: під час побудови запиту, гідрації і `qualifyColumn`. Кожен виклик — запит до бази, щоб перевірити, чи є таблиця `products` / `product_variants`. Таблиці перейменовано ще у квітні, тож запасні гілки `animal_listings` / `animal_variants` давно мертві.
- **Ефект.** Найбільший. На категорії з 24 картками зникне близько 1060 із 1432 запитів (816 + 246 `sqlite_master` + `pragma`). На MySQL це сотні мілісекунд або кілька секунд TTFB. Найбільше виграють каталог, пошук і головна.
- **Як виправити.** Запам'ятовувати результат у `static`-змінній (один раз на процес) або просто повертати `'products'` / `'product_variants'`. Ім'я зовнішнього ключа в зв'язках теж зробити константою `product_id`.
- **Складність:** мала.

### 2. N+1 у картці товару (каталог, головна, рекомендації)

- **Де:**
  - `resources/views/catalog/partials/product-card.blade.php:15` викликає `ListingDefaultOptionPrice::addonTotalForProduct()`. Той на кожну картку робить `Schema::hasTable('option_groups')` і запит `OptionGroup::systemCategoryGroupIdForCatalog()` (`app/Support/ListingDefaultOptionPrice.php:38-39`).
  - `product-card.blade.php:27`: `$listing->isOrderable()` ліниво вантажить `variants`, бо в `CatalogController.php:1004-1007` товари сторінки беруться без `with('variants')`. Те саме на головній: `HomeController.php:126`.
  - `product-card.blade.php:30`: `ListingOptionSelectionService::missingRequiredOptionGroupNames()` на кожну картку робить окремі запити до `option_groups` і `option_values`.
  - `product-card.blade.php:11`: для комплекту `BundleShowPageService::selectionIsCompleteForBundle()` — аналогічно.
  - `OptionGroup::systemCategoryGroupId()` (`app/Models/OptionGroup.php:190-197`) не запам'ятовується: 7–10 однакових запитів навіть на сторінці без карток, 49–97 на каталозі.
- **Ефект.** Зараз приблизно 9 запитів на картку *після* виправлення п. 1. Якщо завантажувати `variants` заздалегідь, запам'ятовувати `systemCategoryGroupId` і один раз на сторінку брати групи й значення опцій, лишиться кілька запитів на всю сітку незалежно від `per_page`.
- **Складність:** середня. Перші два пункти малі. Пакетне завантаження опцій для `missingRequiredOptionGroupNames` — середнє.

### 3. Каталог вантажить і сортує весь відфільтрований набір у PHP

- **Де:** `app/Http/Controllers/CatalogController.php:939-978` і `paginateCatalogRows()` (`:821`).
- **Що не так.**
  - На кожен запит вибираються **всі** товари й комплекти, що пройшли фільтр, з `withCount('favoritedByUsers')`.
  - Для кожного комплекту рахуються два корельовані підзапити: сума цін і «популярність» (`:798-820`).
  - Потім усе сортується або перемішується (`random`) у PHP і ріжеться на сторінки. Те саме робиться для кожного AJAX-фрагмента «показати ще» (`X-Catalog-Fragment`).
  - Фільтр категорії складається з `OR` між колонками, JSON у `variant_options` (`JSON_TABLE` / `JSON_CONTAINS`) і `orWhereHas('categories')` (`:296-336`). Через це індекси не працюють, і база щоразу проходить усю таблицю `products` (повний скан).
- **Ефект.** Поки товарів кілька сотень, це терпимо: на продакшені категорія відповідає 0.65 с проти 0.33 с без фільтра. Час росте лінійно з розміром каталогу.
- **Як виправити:**
  1. Для сортувань, крім `random`, робити `ORDER BY` і `LIMIT` у SQL; товари й комплекти можна об'єднати через `UNION`.
  2. Для `random` кешувати список `[type, id]` для комбінації фільтра на 5–10 хвилин (`Cache::remember`) і перемішувати вже його за `mix`.
  3. Далі перевести фільтр категорій лише на таблицю `product_categories`, яка вже має індекси, і прибрати JSON-гілку.
- **Складність:** середня (1–2), велика (3).

### 4. 82 КБ перекладів `window.__SHOP` в HTML кожної сторінки

- **Де:** `resources/views/layouts/shop.blade.php:195`: `window.__SHOP = @json(trans('shop'))`.
- **Що не так.** Туди йдуть усі 477 ключів `lang/*/shop.php`, ще й з `\uXXXX`-екрануванням кирилиці: 83 КБ проти 38 КБ без екранування. Читає ці дані лише `resources/views/checkout/index.blade.php:1268`, і то **19 ключів** (`checkout_*`, `js_*`).
- **Ефект:** мінус 82 КБ HTML на кожній сторінці (близько 10–12 КБ gzip), швидший розбір на телефонах.
- **Як виправити:** прибрати з layout і віддавати на сторінці оформлення лише потрібні ключі через `@json([...], JSON_UNESCAPED_UNICODE)`.
- **Складність:** мала.

### 5. Ще близько 115–180 КБ inline-JS і CSS, які браузер не може закешувати

- **Де** (розміри в зібраному HTML):
  - `catalog/partials/shop-catalog-scripts.blade.php`: **52.7 КБ** JS на `/`, `/catalog` і сторінці товару. Динамічний у ньому лише `shopAuth` (`:1-11`).
  - Кошик-шухляда в `layouts/shop.blade.php` після `@stack('scripts')`: 18.7 КБ.
  - `partials/shop-search-suggest.blade.php`: 13.5 КБ.
  - Скрипт на 23 КБ (горизонтальний свайп каруселей).
  - `catalog/partials/category-header-banner.blade.php`: `<style>` 338 рядків (`:13-351`) і `<script>` 260 рядків (`:449-708`), близько 8.4 КБ JS і близько 10 КБ CSS.
  - На сторінці товару в `catalog/show.blade.php` inline `<style>` займає **66 КБ**.
- **Що не так.** Inline-код щоразу приходить разом із HTML (`Cache-Control: no-cache`), тож повторні візити й переходи між сторінками його не кешують.
- **Ефект.** HTML зменшиться з 330–400 КБ приблизно до 80–120 КБ (gzip з 60–70 КБ приблизно до 20–25 КБ). TTFB і First Contentful Paint на мобільних стануть меншими.
- **Як виправити.** Винести це у Vite-модулі (`resources/js/*.js`, `resources/css/*.css`), а динамічні дані передавати через `data-*` або маленький `<script type="application/json">`.
- **Складність:** середня (багато механічної роботи, ризик регресій у JS).

### 6. Немає довгого кешування статики (`/build`, `/storage`)

- **Де:** `public/.htaccess` (немає `Expires` / `Cache-Control`); на продакшені відповідає nginx.
- **Що не так.** Файли `build/assets/*-HASH.css|js` мають хеш в імені, і їх можна кешувати на рік з `immutable`. Зараз браузер щоразу перепитує сервер (умовні запити) або тримає файли недовго. Те саме з фото `storage/**/*.webp` (12 КБ на картку).
- **Ефект:** помітно швидші повторні візити, менше запитів до сервера.
- **Як виправити:** `Cache-Control: public, max-age=31536000, immutable` для `/build/assets/`, `max-age=2592000` для `/storage/`. Якщо панель хостингу не дає налаштувати nginx, спробувати блок `mod_headers` / `mod_expires` у `.htaccess` (працює, якщо за nginx стоїть Apache).
- **Складність:** мала.

### 7. Загальні дані шапки й футера читаються кілька разів і без кешу

- **Де:**
  - `ShopHeaderSetting::record()` викликається тричі за запит: `app/Support/ShopHeaderViewData.php:17,38,52`, щоразу `firstOrCreate`.
  - `ShopIntegrationSetting::record()` і `query()->first()` — 3–4 рази: `AppServiceProvider.php:55`, `ShopFooterViewData.php:32`, `ProductShowPageService.php:165`, `home.blade.php:13-14` (там ще й `Schema::hasTable` у шаблоні).
  - Футер (`ShopFooterViewData::forRequest`) — 6 запитів: бренд, колонки, посилання, інфосторінки, FAQ.
  - Банери (`ShopHeaderViewData::activeBannersBySlot`, `:76`). Для кожного банера `Storage::exists` (`:119`), ще дві файлові перевірки в `ListingPhotoDerivative::isUsable` і окремий запит slug товару (`:167`).
  - `ShopContact`-пункти, `ShopHomeBenefit::forStorefront()`, `ShopHomeProductSection::forStorefront()` (обидва з `Schema::hasTable`). `ShopCatalogPaginationSetting::perPage()`.
- **Ефект:** 15–25 запитів і кілька файлових перевірок на *кожній* сторінці, які майже ніколи не змінюються.
- **Як виправити:**
  1. Запам'ятовувати `record()` на час запиту (`once(fn () => ...)`).
  2. Зібрані масиви шапки, футера й банерів класти в `Cache::remember` з версією і скидати її в `saved`/`deleted` моделей, як уже зроблено в `StorefrontCategoryTreeCache` (`app/Support/StorefrontCategoryTreeCache.php`).
- **Складність:** мала (запам'ятовування), середня (кеш зі скиданням).

### 8. Сторінка товару: запити ростуть як «групи опцій × варіанти»

- **Де:** `app/Services/ProductShowPageService.php`:
  - `optionGroupPinsAnyVisibleVariant()` (`:674`) для кожної групи перебирає всі варіанти й викликає `storefrontVariantOptionsMap()`. А той через `variantOptionsMapFromPairs()` (`:708`) робить запит `OptionGroup ... pluck('selection_mode')`. Те саме в `buildVariantsPayload()` (`:548`) на кожен варіант.
  - `makeOptionBlock()` (`:975`, `:980`) робить 2 запити на групу.
  - Назви категорій (`:72`, `:87`) беруться окремими запитами, хоча вже є в `$nav['categoryValues']`.
  - `ShopIntegrationSetting::query()->first()` (`:165`).
- **Ефект.** Товар із 3 варіантами дає 10 однакових запитів `selection_mode`. З 20 варіантами і 3 групами їх буде 60–80.
- **Як виправити:** один раз на сторінку завантажити всі `OptionGroup` і `OptionValue` для товару в мапу.
- **Складність:** середня.

### 9. `BundlePricingService::quote()`: N+1 за позиціями комплекту

- **Де:** `app/Services/BundlePricingService.php:30-36` і `:80` / `:83`. На кожну позицію `quoteProductVariant()` або `quoteProduct()` робить окремий запит `promotion_targets`, плюс ще один на акції самого комплекту (`:36`). У каталозі це викликається в циклі (`CatalogController.php:1035-1037`), так само в кошику (`CartDrawerService`) і на сторінці комплекту.
- **Ефект:** (позицій + 1) запитів на кожен комплект; на сторінці «Комплекти» з 24 карток — сотні запитів.
- **Як виправити:** додати `quoteMany(Collection $bundles)`, який одним запитом бере акції для всіх варіантів і товарів, а другим — для всіх комплектів (за зразком `VariantPricingService::quoteManyProducts`).
- **Складність:** середня.

### 10. Дрібні повторювані запити в `CatalogController`

- **Де:**
  - `DB::scalar('SELECT VERSION()')` виконується двічі на кожен фільтр категорії (`CatalogController.php:356`, `:419`).
  - `DbSchema::hasColumn` / `hasTable` на кожен запит (`:106-108`, `:318`, `:328`, `:577`).
- **Ефект:** 5–8 запитів на сторінку (на MySQL кожен — це `information_schema`).
- **Як виправити:** запам'ятовувати в `static` або в конфігу. Колонки `parent_id`, `category_*` і таблиця `product_categories` давно є в усіх середовищах, тож перевірки можна прибрати зовсім.
- **Складність:** мала.

### 11. Продакшен-конфіг і деплой

- **Де:** `env.production.TEMPLATE`, `DEPLOY-RELEASE-v1.1.48.txt:43-45`.
- **Добре:** `APP_DEBUG=false`, `LOG_LEVEL=error`, `composer install --no-dev --optimize-autoloader` (`hosting/prepare-deploy.sh:16`), `config:cache` і `route:cache` у кроках деплою.
- **Що не так:**
  - Після `optimize:clear` не запускається `view:cache` і `event:cache`. Перший відвідувач кожного шаблону чекає компіляції Blade.
  - Немає `php artisan filament:optimize` (кеш компонентів та іконок Filament, прискорює адмінку).
  - `CACHE_STORE=database` і `SESSION_DRIVER=database`. Кожен запит читає й пише сесію в MySQL (плюс `lottery [2,100]` на прибирання), а `Cache::get` / `remember` теж іде в БД. На спільному хостингу без Redis зазвичай швидше `CACHE_STORE=file`. Сесії можна лишити в БД або перенести у `file`.
  - OPcache ніде не згадано. Варто перевірити в селекторі PHP (CloudLinux `alt-php83`): `opcache.enable=1`, `opcache.memory_consumption ≥ 128`, `opcache.max_accelerated_files ≥ 20000`. Бажано ще `opcache.validate_timestamps=0` (тоді після деплою треба перезапустити PHP або скинути OPcache).
- **Ефект:** 10–30 % TTFB залежно від того, чи увімкнено OPcache.
- **Як виправити:** замінити три рядки на `php artisan optimize` (config, routes, events, views) плюс `php artisan filament:optimize`.
- **Складність:** мала.

### 12. Аналітика пише в БД синхронно до відправки відповіді

- **Де:** `app/Http/Middleware/RecordShopAnalytics.php:14-17`, `app/Services/ShopAnalytics.php` (`ready()` на `:1132-1141` робить 3 `Schema::hasTable` і викликається двічі).
- **Що не так.** На кожен перегляд виконується 6–10 запитів, з них 2–3 `INSERT` / `UPDATE` (події, присутність, раз на хвилину зразок онлайну), і все це **до** відправки відповіді. Боти теж рахуються.
- **Ефект:** мінус 6–10 запитів у TTFB кожної сторінки.
- **Як виправити:** перенести запис у `terminate()`, тоді на PHP-FPM він піде після `fastcgi_finish_request`. Запам'ятовувати `ready()`, відсікати ботів за User-Agent.
- **Складність:** мала.

### 13. Шрифти Google Fonts блокують відображення сторінки

- **Де:** `resources/views/layouts/shop.blade.php:191-193`.
- **Що не так.** Підключено 2 сімейства і 12 накреслень (Rubik 400/500/600/700/800 і italic 400/600; Source Sans 3 400/600/700 і italic 400/600). Стилі зі стороннього домену блокують відображення сторінки, бо потрібні DNS, TLS і окремий CSS. Source Sans 3 використовується лише як `--font-reading` для тексту опису.
- **Ефект:** −100…300 мс до першого відображення на мобільних, менше «стрибків» шрифту.
- **Як виправити.** Перенести woff2 у свій домен: підмножини cyrillic і latin, `font-display: swap`, `preload` 1–2 основних накреслень. Прибрати накреслення, яких немає в CSS (італіки варто перевірити).
- **Складність:** мала або середня.

### 14. Великі CSS-бандли на всіх сторінках

- **Де:** `vite.config.js`, `resources/css/shop.css` (247 КБ вихідного коду), `resources/css/category-hub.css` (168 КБ).
- **Що не так.** У збірці `shop-*.css` важить 140 КБ (23 КБ gzip), `category-hub-*.css` — 155 КБ (лише 12 КБ gzip, тобто багато повторів). Обидва блокують рендер на кожній сторінці, зокрема на оформленні й в акаунті.
- **Ефект:** менше роботи з розбору CSS на слабких телефонах; у мережі виграш помірний.
- **Як виправити.** Знайти дублікати й мертві правила (наприклад, Coverage у Chrome DevTools). Розбити `category-hub.css` на критичну частину для шапки й решту, яку підключати лише там, де є каталог.
- **Складність:** середня.

### 15. Індекси

- **Добре:**
  - `promotion_targets(target_type, target_id)`, `product_variants(product_id, is_visible, is_available)`, `product_categories(category_value_id, product_id)`, `shop_home_list_items(list, sort_order)`, усі індекси `shop_analytics_*`.
  - `products.category_parent_value_id` і `category_value_id` та `bundle_items.bundle_id` і `product_id` створено через `foreignId()->constrained()`. На MySQL/InnoDB зовнішній ключ автоматично отримує індекс.
- **Чого бракує** (ефект малий, поки каталог невеликий):
  - `products(is_available, product_type)`: каталог завжди фільтрує за цими двома полями. Є лише окремий `product_type`.
  - `bundles(is_visible, is_active)`.
  - `promotions(is_active, starts_at, ends_at)`: потрібен для `EXISTS`-підзапиту фільтра «Акції».
- **Чого індексом не виправити:** фільтр за JSON `variant_options` (див. п. 3).
- **Складність:** мала (одна міграція).

### 16. Зображення: загалом добре

- **Добре:**
  - Картки йдуть як `*.card.webp` (близько 12 КБ).
  - Банери — `*.banner.webp`.
  - `loading="lazy"` стоїть на 23–25 із 30 `<img>`, на першому рядку `fetchpriority`, `width` і `height` є майже скрізь.
- **Дрібниця.** `ListingPhotoDerivative::isUsable()` (`app/Support/ListingPhotoDerivative.php:79-84`) робить на кожне фото два звернення до файлової системи (`exists` і `size`). На 48 картках це близько 100 `stat`. Можна запам'ятовувати на час запиту або зберігати ознаку похідного файлу в `photo_meta`.
- **Складність:** мала.

### Інше (не швидкість сайту, але варто знати)

- `hosting/` займає **6.7 ГБ** старих пакетів `zoogle-v1.1.15 … v1.1.48` (і `.tar.gz`). Варто переконатися, що ця тека не потрапляє на сервер.
- `catalog/show.blade.php` має 2482 рядки, `checkout/index.blade.php` — 136 КБ. Підтримувати їх важко; їх варто розбити разом із п. 5.

---

## Швидкі покращення: зробити першими

1. **Запам'ятовувати `getTable()` і `hasColumn` у моделях Product і ProductVariant** (п. 1, мала складність). Найбільший ефект на БД: мінус 70–75 % запитів на сторінках каталогу.
2. **Завантажувати `variants` для карток і запам'ятовувати `OptionGroup::systemCategoryGroupId()`, `ShopHeaderSetting::record()`, `ShopIntegrationSetting::record()` на час запиту** (п. 2, 7, 10; мала складність). Ще мінус 50–200 запитів на каталозі й мінус 10–15 на кожній сторінці.
3. **Прибрати `window.__SHOP` з layout** і передавати 19 ключів лише в checkout (п. 4, мала складність). Мінус 82 КБ HTML на кожній сторінці.
4. **Довгий `Cache-Control` для `/build/assets` і `/storage`**, плюс `php artisan optimize` і `filament:optimize` у кроках деплою, плюс перевірка OPcache (п. 6, 11; мала складність).
5. **Перенести запис аналітики в `terminate()`** і запам'ятовувати `ShopAnalytics::ready()` (п. 12, мала складність).

Після кроків 1–2 варто повторити заміри. Очікувано на категорії з 24 картками лишиться **приблизно 40–60 запитів замість 1432**, і далі вже має сенс братися за п. 3, 5 і 8.

---

## Що зроблено (2026-11-05, після огляду)

### Результат на копії бази з 204 товарами і 21 комплектом

Запити рахувались на сторінку з прогрітим кешем, пам'ять запиту щоразу скидалась, як у PHP-FPM. Час — повна обробка запиту в PHP (SQLite).

| Сторінка | Запитів було → стало | HTML було → стало |
|---|---|---|
| `/` | 172 → **13** | 397 → **153 КБ** |
| `/catalog` | 33 → **2** | 326 → **126 КБ** |
| `/catalog?category=1` (24 картки) | 1431 → **14** | 475 → **276 КБ** |
| `/catalog?category=1&per_page=48` | 2607 → **14** | 593 → **394 КБ** |
| `/catalog?bundles=1` | 1962 → **14** (512 → 87 мс) | 428 → **229 КБ** |
| `/catalog?q=test` | 1650 → **14** | 464 → **265 КБ** |
| сторінка товару | 225 → **22** | 378 → **108 КБ** |
| сторінка комплекту | 130 → **17** | 235 → **86 КБ** |
| `/bundles` | 2458 → **11** (545 → 52 мс) | 207 → **78 КБ** |

Видимий вигляд не змінився. Знімки headless Chromium старої і нової версії збігаються піксель у піксель, крім випадкового слайда банера й анімації логотипа. Кошик, шухляда кошика, пошукові підказки, банери і оформлення замовлення перевірені в браузері, помилок у консолі немає.

### Зміни

1. **Перевірки схеми** (п. 1, 10). Новий `App\Support\SchemaCache` запам'ятовує «так» для `hasTable` / `hasColumn` на процес. Його використовують `CatalogProductsTable`, `CatalogProductVariantsTable`, зв'язки `Product`, `ProductVariant`, `OrderItem` і всі вітринні сервіси. `SELECT VERSION()` у `CatalogController` виконується раз на процес.
2. **Пам'ять запиту** (п. 2, 7). `App\Support\StorefrontMemo` зберігає:
   - `OptionGroup::systemCategoryGroupId()`;
   - `ShopHeaderSetting::storefront()` і `ShopIntegrationSetting::storefront()` (`record()` для адмінки не змінено);
   - групи й значення опцій (`App\Support\StorefrontOptionCatalog`);
   - блоки опцій товару (`ListingOptionSelectionService::storefrontOptionBlocks`);
   - перевірки похідних фото.

   Пам'ять скидається при збереженні або видаленні відповідних моделей і в `Tests\TestCase::setUp`.
3. **N+1 у картках** (п. 2). Варіанти завантажуються наперед у каталозі, на головній і в рекомендаціях. `CatalogCategoryTree::ancestorsChainFromNode` більше не робить запит на кожен рівень.
4. **Ціни комплектів** (п. 9). `BundlePricingService::quoteMany()` бере акції товарів, варіантів і комплектів трьома запитами (`VariantPricingService::preloadTargets` / `targetsFor`). Використовується в каталозі та на `/bundles`.
5. **Кеш шапки, футера й головної** (п. 7). `App\Support\StorefrontViewCache` (10 хв, з версією) кешує футер, контакти шапки, банери, блоки «Переваги» і «Секції товарів». Версію скидають події `saved` / `deleted` 13 моделей і команда `shop:make-photo-derivatives`.
6. **Аналітика** (п. 12). Запис у БД перенесено в `RecordShopAnalytics::terminate()`, тобто після відправки відповіді. Cookie відвідувача, як і раніше, видається у відповіді.
7. **`window.__SHOP`** (п. 4). Прибрано з layout. Оформлення замовлення отримує лише свої 19 рядків.
8. **Inline-JS** (п. 5). Сім блоків (каталог, шухляда кошика, компактна шапка, пошукові підказки, банери, футер, каруселі головної) винесено в `public/js/storefront/*.js`. Вони підключаються з `defer` і `?v=` (час зміни файлу, `App\Support\StorefrontAsset`). Це звичайні, не модульні скрипти, щоб не змінити нестрогий режим і порядок виконання. Дані користувача для каталогу передаються через `window.zoogleShopAuth`.
9. **Inline-CSS** (п. 5). Дев'ять великих `<style>` (сторінка товару, акаунт, оформлення, комплект, головна, каруселі, банер, відстеження замовлення, статті) винесено в `resources/css/storefront/*.css`. Їх збирає Vite (з мініфікацією), підключаються на тому самому місці `@stack('styles')`, тож каскад не змінився.
10. **Індекси** (п. 15). Міграція `2026_11_05_210000_add_storefront_filter_indexes`: `products(is_available, product_type)`, `bundles(is_visible, is_active)`, `promotions(is_active, starts_at, ends_at)`. Перевірено `migrate` → `rollback` → `migrate` на копії бази.
11. **Деплой** (п. 6, 11). `hosting/DEPLOY-UK.md` і `ZOOOGLE-UPLOAD-README.txt` тепер використовують `php artisan optimize` і `filament:optimize`. Додано налаштування OPcache і правила nginx для `Cache-Control` статики.
12. **Тести.** `tests/Feature/StorefrontQueryBudgetTest.php` перевіряє, що кількість запитів каталогу і списку комплектів не росте разом з кількістю карток. На старому коді тест падає (117 → 384 запити). `HomeAndCatalogGridTest` тепер перевіряє `js/storefront/catalog.js`, а не inline-текст. Усього 250 тестів, усі зелені.

### Що свідомо не зроблено

- **Повне перероблення вибірки каталогу на SQL `ORDER BY` / `LIMIT`** (п. 3). Після виправлень сторінка робить один запит на весь відфільтрований список; з тисячами товарів варто повернутись до цього.
- **Свої шрифти на власному домені** (п. 13): потрібно завантажити woff2-файли. Неблокуюче завантаження Google Fonts я пробував, але відкотив: воно давало помітний зсув тексту (CLS) після підвантаження шрифту. Курсив Rubik потрібен, бо стиль чипів шапки в адмінці може бути italic.
- **Чистка дублікатів у `shop.css` і `category-hub.css`** (п. 14): високий ризик візуальних регресій без окремої перевірки дизайну.
- **Правила кешу в `public/.htaccess`.** Продакшен віддає статику через nginx, тож ці правила там не діяли б, а `<If>` у `.htaccess` на частині хостингів дає 500. Тому правила описано для панелі nginx у `hosting/DEPLOY-UK.md`.
- **`category-subcategory-filter-styles` лишився inline**: він приходить разом з AJAX-фрагментом результатів.
- **`CACHE_STORE=database` не змінено**: на ньому тримаються захист від подвійного оформлення і ліміти запитів.

### Перед деплоєм

1. `npm run build` уже виконано, `public/build` оновлено.
2. На сервері виконати `php artisan migrate --force` (нова міграція індексів), потім `php artisan optimize` і `php artisan filament:optimize`.
3. Нова тека `public/js/storefront/` має потрапити на сервер (пакет копіює `public/` повністю).
