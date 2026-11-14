@extends('layouts.shop')

@section('title', $title.' — ZOOGLE')

@php
    $children = is_array($children ?? null) ? $children : [];
    $breadcrumbs = is_array($breadcrumbs ?? null) ? $breadcrumbs : [];
    $hasChildren = $children !== [];
    $seoSource = trim((string) ($body ?? ''));
    if ($seoSource === '' && $hasChildren) {
        $seoSource = collect($children)
            ->map(static fn (array $child): string => trim((string) ($child['title'] ?? '')))
            ->filter(static fn (string $t): bool => $t !== '')
            ->implode('. ');
    }
    $infoSeoDescription = trim(preg_replace('/\s+/u', ' ', strip_tags($seoSource)));
    if ($infoSeoDescription === '') {
        $infoSeoDescription = __('shop.static_seo_default');
    }
@endphp

@section('meta_description', \Illuminate\Support\Str::limit($infoSeoDescription, 155, ''))
@section('canonical_url', url()->current())

@section('content')
    <article class="info-page" aria-labelledby="info-page-title">
        @if($breadcrumbs !== [])
            <nav class="info-page__breadcrumbs" aria-label="{{ __('shop.info_page_breadcrumb_aria') }}">
                <ol class="info-page__breadcrumbs-list">
                    <li class="info-page__breadcrumbs-item">
                        <a href="{{ route('home') }}" class="info-page__breadcrumbs-link">{{ __('shop.info_page_breadcrumb_home') }}</a>
                    </li>
                    @foreach($breadcrumbs as $crumb)
                        @php
                            $crumbSlug = trim((string) ($crumb['slug'] ?? ''));
                        @endphp
                        @if($crumbSlug !== '')
                            <li class="info-page__breadcrumbs-item">
                                <a href="{{ route('info.show', ['slug' => $crumbSlug]) }}" class="info-page__breadcrumbs-link">{{ e((string) ($crumb['title'] ?? $crumbSlug)) }}</a>
                            </li>
                        @endif
                    @endforeach
                    <li class="info-page__breadcrumbs-item info-page__breadcrumbs-item--current" aria-current="page">
                        <span>{{ $title }}</span>
                    </li>
                </ol>
            </nav>
        @endif

        <div class="info-page__panel">
            <h1 id="info-page-title" class="info-page__title">{{ $title }}</h1>

            @if(\App\Support\RichTextSanitizer::hasVisibleContent($body ?? null))
                <div class="info-page__body">
                    {!! \App\Support\RichTextSanitizer::toHtml($body) !!}
                </div>
            @endif

            @if($hasChildren)
                <div class="info-page__children-wrap">
                    <h2 class="info-page__children-heading">{{ __('shop.info_page_children_heading') }}</h2>
                    <ul class="info-page__children">
                        @foreach($children as $child)
                            @php
                                $childSlug = trim((string) ($child['slug'] ?? ''));
                            @endphp
                            @if($childSlug !== '')
                                <li class="info-page__child-item">
                                    <a href="{{ route('info.show', ['slug' => $childSlug]) }}" class="info-page__child-card">{{ e((string) ($child['title'] ?? $childSlug)) }}</a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            @elseif(! \App\Support\RichTextSanitizer::hasVisibleContent($body ?? null))
                <div class="info-page__body">
                    {!! nl2br(e(__('shop.info_page_empty_body'))) !!}
                </div>
            @endif
        </div>
    </article>
@endsection
