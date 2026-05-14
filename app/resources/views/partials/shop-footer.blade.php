<footer class="site-footer" role="contentinfo">
    <div class="container site-footer__grid">
        <div class="site-footer__brand">
            <strong>ZOOGLE</strong>
            <p class="site-footer__muted">Зоотовари та товари для улюбленців — каталог з варіантами та зручним замовленням.</p>
        </div>
        <div class="site-footer__col">
            <span class="site-footer__heading">Покупцям</span>
            <ul class="site-footer__links">
                <li><a href="{{ route('catalog.index') }}">Каталог</a></li>
                <li><a href="{{ route('bundles.index') }}">Комплекти</a></li>
                <li><a href="{{ route('cart.index') }}">Кошик</a></li>
                <li><a href="{{ route('checkout.create') }}">Оформлення замовлення</a></li>
                <li><a href="{{ route('delivery-payment') }}">Доставка та оплата</a></li>
            </ul>
        </div>
        <div class="site-footer__col">
            <span class="site-footer__heading">Про нас</span>
            <ul class="site-footer__links">
                <li><a href="{{ route('about') }}">Про компанію</a></li>
                <li><a href="{{ route('contacts') }}">Контакти</a></li>
                <li><a href="{{ route('news') }}">Новини</a></li>
                <li><a href="{{ route('privacy') }}">Конфіденційність</a></li>
            </ul>
        </div>
        <div class="site-footer__col">
            <span class="site-footer__heading">Сервіс</span>
            <ul class="site-footer__links">
                <li><a href="/admin" rel="nofollow">Панель адміністратора</a></li>
            </ul>
        </div>
    </div>
    <div class="container site-footer__catalog-layout-wrap">
        <button
            type="button"
            class="site-footer__catalog-layout-btn"
            id="shop-catalog-layout-toggle"
            aria-pressed="false"
        >Каталог на телефоні: два стовпчики (натисніть для одного)</button>
    </div>
    <div class="site-footer__bottom">
        <div class="container site-footer__bottom-inner">
            <span>&copy; {{ date('Y') }} ZOOGLE. Всі права захищені.</span>
        </div>
    </div>
    <script>
        (function () {
            var KEY = 'shopCatalogOneCol';
            var root = document.documentElement;
            var btn = document.getElementById('shop-catalog-layout-toggle');
            function isOneCol() {
                try {
                    return localStorage.getItem(KEY) === '1';
                } catch (e) {
                    return false;
                }
            }
            function apply() {
                var on = isOneCol();
                root.classList.toggle('shop-layout--catalog-one-col', on);
                if (btn) {
                    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                    btn.textContent = on
                        ? 'Каталог на телефоні: один стовпчик (натисніть для двох)'
                        : 'Каталог на телефоні: два стовпчики (натисніть для одного)';
                }
            }
            function toggle() {
                try {
                    if (isOneCol()) {
                        localStorage.removeItem(KEY);
                    } else {
                        localStorage.setItem(KEY, '1');
                    }
                } catch (e) {}
                apply();
            }
            if (btn) {
                btn.addEventListener('click', toggle);
            }
            apply();
        })();
    </script>
</footer>
