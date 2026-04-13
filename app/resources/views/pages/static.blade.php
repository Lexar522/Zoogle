@extends('layouts.shop')

@section('title', $title . ' — ZOOGLE')

@section('content')
    <div class="card">
        <h1>{{ $title }}</h1>
        <div class="muted" style="margin:0; line-height:1.6;">{!! nl2br(e($body ?? 'Сторінка в розробці. Оновлений контент зʼявиться незабаром.')) !!}</div>
    </div>
@endsection
