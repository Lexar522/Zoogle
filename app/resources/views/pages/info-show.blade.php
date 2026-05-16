@extends('layouts.shop')

@section('title', $title.' — ZOOGLE')

@php
    $infoSeoDescription = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($body ?? ''))));
    if ($infoSeoDescription === '') {
        $infoSeoDescription = __('shop.static_seo_default');
    }
@endphp

@section('meta_description', \Illuminate\Support\Str::limit($infoSeoDescription, 155, ''))
@section('canonical_url', url()->current())
@section('og_title', $title.' — ZOOGLE')
@section('og_description', \Illuminate\Support\Str::limit($infoSeoDescription, 155, ''))

@section('content')
    <article class="info-page" aria-labelledby="info-page-title">
        <div class="info-page__panel">
            <h1 id="info-page-title" class="info-page__title">{{ $title }}</h1>
            <div class="info-page__body">
                @if(filled($body))
                    {!! nl2br(e($body)) !!}
                @else
                    {!! nl2br(e(__('shop.info_page_empty_body'))) !!}
                @endif
            </div>
        </div>
    </article>
@endsection
