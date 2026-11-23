@extends('layouts.shop')

@section('title', $charity['title'].' — ZOOGLE')
@section('meta_description', \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags($charity['body_html']))) ?: $charity['title'], 155, ''))
@section('canonical_url', route('charity'))

@section('content')
    <article class="info-page charity-page" aria-labelledby="charity-page-title">
        <div class="info-page__panel">
            <h1 id="charity-page-title" class="info-page__title">{{ $charity['title'] }}</h1>

            @if ($charity['body_html'] !== '')
                <div class="info-page__body">{!! $charity['body_html'] !!}</div>
            @endif

            @if ($charity['jar_url'] !== null)
                <p class="charity-page__actions">
                    <a class="btn btn-buy charity-page__donate" href="{{ $charity['jar_url'] }}" target="_blank" rel="noopener noreferrer">{{ $charity['button_label'] }}</a>
                </p>
            @endif
        </div>
    </article>
@endsection
