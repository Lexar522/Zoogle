{{-- Низ бокового меню адмінки: версія сайту й дата останньої заливки (останнього artisan optimize) --}}
@php
    $siteVersion = \App\Support\SiteRelease::version();
    $deployedAt = \App\Support\SiteRelease::deployedAt();
@endphp
@if ($siteVersion !== null)
    <div class="zg-site-release" style="padding: 12px 16px 16px; font-size: 0.75rem; line-height: 1.4; color: rgb(107 114 128);">
        Версія {{ $siteVersion }}
        @if ($deployedAt !== null)
            <br>оновлено {{ $deployedAt->format('d.m.Y H:i') }}
        @endif
    </div>
@endif
