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
    <div class="site-footer__bottom">
        <div class="container site-footer__bottom-inner">
            <span>&copy; {{ date('Y') }} ZOOGLE. Всі права захищені.</span>
        </div>
    </div>
</footer>
