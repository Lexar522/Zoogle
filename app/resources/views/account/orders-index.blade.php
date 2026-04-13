@extends('account.layout')

@section('title', 'Мої замовлення — ZOOGLE')

@section('account_content')
    <section class="account-card">
        <div class="account-card__head account-card__head--hero">
            <h1>Мої замовлення</h1>
        </div>
        @if ($orders->isEmpty())
            <p class="account-empty" style="margin:0;">Поки що порожньо.</p>
        @else
            <div class="account-table-wrap">
                <table class="account-table">
                    <thead>
                        <tr>
                            <th>Номер</th>
                            <th>Дата</th>
                            <th>Сума</th>
                            <th>Статус</th>
                            <th>Оплата</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td>
                                    <a href="{{ route('account.orders.show', $order) }}">{{ $order->number }}</a>
                                </td>
                                <td>{{ $order->placed_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                <td>{{ number_format((float) $order->total, 2) }} UAH</td>
                                <td>{{ $order->status }}</td>
                                <td>{{ $order->payment_status }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="margin-top:1.1rem;">{{ $orders->links() }}</div>
        @endif
    </section>
@endsection
