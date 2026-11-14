import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/shop.css',
                'resources/css/category-hub.css',
                'resources/css/storefront/account.css',
                'resources/css/storefront/bundle-show.css',
                'resources/css/storefront/care-show.css',
                'resources/css/storefront/checkout.css',
                'resources/css/storefront/header-banner.css',
                'resources/css/storefront/home.css',
                'resources/css/storefront/home-carousel.css',
                'resources/css/storefront/order-track.css',
                'resources/css/storefront/product-show.css',
                'resources/js/app.js',
                'resources/js/product-show-page.js',
                'resources/js/bundle-show-page.js',
                'resources/js/catalog-card-media.js',
                'resources/js/category-hub.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
