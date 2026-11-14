@if (\App\Support\MarketingTags::enabled())
    <div
        data-cookie-banner
        hidden
        role="region"
        aria-label="Cookie"
        style="position:fixed;left:16px;right:16px;bottom:16px;z-index:1000;max-width:560px;margin:0 auto;padding:14px 16px;border-radius:12px;background:#fff;color:#1f2937;box-shadow:0 8px 30px rgba(0,0,0,.2);"
    >
        <p style="margin:0 0 10px;line-height:1.45;">{{ __('shop.cookie_banner_text') }}</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" class="btn" data-cookie-accept>{{ __('shop.cookie_banner_accept') }}</button>
            <button type="button" class="btn secondary" data-cookie-decline>{{ __('shop.cookie_banner_decline') }}</button>
        </div>
    </div>
    <script>
    (function () {
        var ga4Id = @json(\App\Support\MarketingTags::ga4Id());
        var pixelId = @json(\App\Support\MarketingTags::metaPixelId());
        var storageKey = 'zoogle_cookie_consent';
        var queue = [];
        var loaded = false;
        var metaEvents = { view_item: 'ViewContent', add_to_cart: 'AddToCart', begin_checkout: 'InitiateCheckout', purchase: 'Purchase' };

        function readConsent() {
            try { return localStorage.getItem(storageKey); } catch (e) { return null; }
        }
        function saveConsent(value) {
            try { localStorage.setItem(storageKey, value); } catch (e) {}
        }
        function send(name, params) {
            if (window.gtag) {
                window.gtag('event', name, params);
            }
            if (window.fbq && metaEvents[name]) {
                var items = params.items || [];
                window.fbq('track', metaEvents[name], {
                    currency: params.currency || 'UAH',
                    value: params.value || 0,
                    content_type: 'product',
                    content_ids: items.map(function (item) { return item.id; })
                });
            }
        }
        function load() {
            if (loaded) return;
            loaded = true;
            if (ga4Id) {
                var ga = document.createElement('script');
                ga.async = true;
                ga.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(ga4Id);
                document.head.appendChild(ga);
                window.dataLayer = window.dataLayer || [];
                window.gtag = function () { window.dataLayer.push(arguments); };
                window.gtag('js', new Date());
                window.gtag('config', ga4Id);
            }
            if (pixelId) {
                !function (f, b, e, v, n, t, s) {
                    if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
                    if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = [];
                    t = b.createElement(e); t.async = !0; t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s);
                }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
                window.fbq('init', pixelId);
                window.fbq('track', 'PageView');
            }
            queue.splice(0).forEach(function (event) { send(event[0], event[1]); });
        }

        window.zgTrack = function (name, params) {
            if (loaded) {
                send(name, params);
            } else {
                queue.push([name, params]);
            }
        };

        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!form || (form.getAttribute('method') || '').toLowerCase() !== 'post') return;
            if (!/\/cart$/.test(form.getAttribute('action') || '')) return;
            var item = window.zgCurrentItem;
            window.zgTrack('add_to_cart', item
                ? { currency: 'UAH', value: item.price, items: [item] }
                : { currency: 'UAH' });
        }, true);

        var consent = readConsent();
        if (consent === 'accepted') {
            load();
            return;
        }
        if (consent === 'declined') {
            return;
        }

        var banner = document.querySelector('[data-cookie-banner]');
        banner.hidden = false;
        banner.querySelector('[data-cookie-accept]').addEventListener('click', function () {
            saveConsent('accepted');
            banner.hidden = true;
            load();
        });
        banner.querySelector('[data-cookie-decline]').addEventListener('click', function () {
            saveConsent('declined');
            banner.hidden = true;
            queue.length = 0;
        });
    })();
    </script>
@endif
