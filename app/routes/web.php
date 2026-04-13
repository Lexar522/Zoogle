<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\BundleController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderTrackController;
use App\Http\Controllers\Payments\LiqPayCallbackController;
use App\Http\Controllers\ProductFavoriteController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('catalog.index');
})->name('home');

Route::get('/login', [GoogleAuthController::class, 'redirect'])->middleware('guest')->name('login');
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/account', [AccountController::class, 'dashboard'])->name('account.index');
    Route::get('/account/orders', [AccountController::class, 'orders'])->name('account.orders.index');
    Route::get('/account/orders/{order}', [AccountController::class, 'orderShow'])->name('account.orders.show');
    Route::get('/account/favorites', [AccountController::class, 'favorites'])->name('account.favorites');

    Route::post('/favorites/toggle', [ProductFavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::post('/favorites/sync', [ProductFavoriteController::class, 'sync'])->name('favorites.sync');
});

Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');

Route::view('/about', 'pages.static', [
    'title' => 'Про zOOGLE',
    'body' => "zOOGLE — зоомагазин із зручним каталогом, варіантами товарів і прозорими цінами.\nМи допомагаємо швидко знайти потрібне для ваших улюбленців і оформити замовлення онлайн.",
])->name('about');

Route::view('/contacts', 'pages.static', [
    'title' => 'Контакти',
    'body' => "Звʼяжіться з нами для консультацій та питань щодо замовлень.\nEmail та телефон будуть опубліковані тут після фінального запуску.",
])->name('contacts');

Route::view('/news', 'pages.static', [
    'title' => 'Новини та статті',
    'body' => "Тут зʼявлятимуться поради з догляду за тваринами, огляди товарів та новини магазину.\nСлідкуйте за оновленнями.",
])->name('news');

Route::view('/delivery-payment', 'pages.static', [
    'title' => 'Доставка та оплата',
    'body' => "Доставка: самовивіз, курʼєр або Нова Пошта — спосіб обирається при оформленні замовлення.\nОплата: онлайн через LiqPay (якщо увімкнено в магазині) або за узгодженням з менеджером для інших способів.\nТочні тарифи доставки уточнюйте під час підтвердження замовлення.",
])->name('delivery-payment');

Route::view('/privacy', 'pages.static', [
    'title' => 'Політика конфіденційності',
    'body' => "Ми обробляємо персональні дані (імʼя, телефон, email, адреса доставки) лише для виконання замовлення та звʼязку з вами.\nДані не передаються третім особам, окрім випадків, передбачених законом (наприклад, службам доставки).\nВи можете надіслати запит на зміну або видалення даних через контактні канали магазину.\n\nВхід через Google: при авторизації ми отримуємо від Google ідентифікатор акаунта, імʼя, email та (за наявності) зображення профілю. Ці дані зберігаються у вашому обліковому записі на сайті для входу, відображення в кабінеті та (де доречно) попереднього заповнення форми замовлення. Ви можете відкликати доступ у налаштуваннях безпеки Google.",
])->name('privacy');
Route::get('/catalog/{slug}', [CatalogController::class, 'show'])->name('catalog.show');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
Route::patch('/cart/{cartKey}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{cartKey}', [CartController::class, 'destroy'])->name('cart.destroy');

Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/payment/{order}', [CheckoutController::class, 'payment'])->name('checkout.payment');
Route::get('/checkout/success/{order}/{token?}', [CheckoutController::class, 'success'])->name('checkout.success');

Route::post('/payments/liqpay/callback', LiqPayCallbackController::class)->name('payments.liqpay.callback');

Route::get('/orders/{order}/track', OrderTrackController::class)->name('orders.track');

Route::get('/bundles', [BundleController::class, 'index'])->name('bundles.index');
Route::get('/bundles/{slug}', [BundleController::class, 'show'])->name('bundles.show');
Route::post('/bundles/{bundle}/add-to-cart', [BundleController::class, 'addToCart'])->name('bundles.add-to-cart');
