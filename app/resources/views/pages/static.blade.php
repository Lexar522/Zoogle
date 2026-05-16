@extends('layouts.shop')

@section('title', $title . ' — ZOOGLE')

@php
    $staticSeoDescription = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($body ?? ''))));
    if ($staticSeoDescription === '') {
        $staticSeoDescription = __('shop.static_seo_default');
    }
@endphp

@section('meta_description', \Illuminate\Support\Str::limit($staticSeoDescription, 155, ''))
@section('canonical_url', url()->current())
@section('og_title', $title.' — ZOOGLE')
@section('og_description', \Illuminate\Support\Str::limit($staticSeoDescription, 155, ''))

@section('content')
    <div class="card">
        <h1>{{ $title }}</h1>
        <div class="muted" style="margin:0; line-height:1.6;">{!! nl2br(e($body ?? __('shop.static_under_development'))) !!}</div>
    </div>
@endsection
