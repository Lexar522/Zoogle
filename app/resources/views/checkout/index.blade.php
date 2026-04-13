@extends('layouts.shop')

@section('title', 'Оформлення замовлення — ZOOGLE')

@php
    $checkoutPrefill = $checkoutPrefill ?? ['customer_name' => '', 'customer_email' => ''];
@endphp

@section('content')
    <div class="card">
        <h1>Оформлення замовлення</h1>
    </div>

    <div class="card">
        <h3>Позиції</h3>
        @foreach ($items as $item)
            <p>
                {{ $item['title'] ?? ($item['product']->title ?? 'Позиція') }}
                @if (($item['line_kind'] ?? 'product') === 'bundle')
                    <span class="muted">· комплект</span>
                @elseif (! empty($item['option_labels'] ?? []))
                    <span class="muted">· {{ implode(', ', $item['option_labels']) }}</span>
                @endif
                — {{ number_format((float) $item['unit_price'], 2) }} × {{ $item['qty'] }} = {{ number_format((float) $item['line_total'], 2) }} UAH
            </p>
            @if (($item['line_kind'] ?? 'product') === 'bundle' && ! empty($item['bundle_items'] ?? []))
                <p class="muted" style="margin-top:-8px;">
                    Склад:
                    {{ collect($item['bundle_items'])->map(fn ($row) => ($row['title'] ?? 'Товар').' × '.((int) ($row['qty'] ?? 1)))->implode(', ') }}
                </p>
            @endif
        @endforeach
        <p><strong>Разом: {{ number_format((float) $total, 2) }} UAH</strong></p>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('checkout.store') }}">
            @csrf
            <div class="row-2">
                <div>
                    <label for="customer_name">Ім'я</label>
                    <input id="customer_name" name="customer_name" value="{{ old('customer_name', $checkoutPrefill['customer_name'] ?? '') }}" required>
                    @error('customer_name') <p class="alert error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="customer_phone">Телефон</label>
                    <input id="customer_phone" name="customer_phone" value="{{ old('customer_phone') }}" required>
                    @error('customer_phone') <p class="alert error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="row-2" style="margin-top: 10px;">
                <div>
                    <label for="customer_email">Email</label>
                    <input id="customer_email" name="customer_email" type="email" value="{{ old('customer_email', $checkoutPrefill['customer_email'] ?? '') }}">
                    @error('customer_email') <p class="alert error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="customer_address">Адреса (додатково)</label>
                    <input id="customer_address" name="customer_address" value="{{ old('customer_address') }}">
                    @error('customer_address') <p class="alert error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div style="margin-top: 14px;">
                <label for="delivery_type">Спосіб доставки</label>
                <select id="delivery_type" name="delivery_type" required>
                    @php($dt = old('delivery_type', \App\Models\Order::DELIVERY_PICKUP))
                    @foreach (\App\Models\Order::deliveryTypeLabels() as $value => $label)
                        <option value="{{ $value }}" @selected($dt === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('delivery_type') <p class="alert error">{{ $message }}</p> @enderror
            </div>

            <div class="row-2" style="margin-top: 10px;" id="delivery-np-fields">
                <div>
                    <label for="delivery_city">Місто (Нова Пошта)</label>
                    <input id="delivery_city" name="delivery_city" value="{{ old('delivery_city') }}">
                    @error('delivery_city') <p class="alert error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="delivery_branch">Відділення / поштомат</label>
                    <input id="delivery_branch" name="delivery_branch" value="{{ old('delivery_branch') }}" placeholder="№ відділення або адреса поштомата">
                    @error('delivery_branch') <p class="alert error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div style="margin-top: 10px;" id="delivery-courier-field">
                <label for="delivery_address">Адреса доставки (кур'єр)</label>
                <textarea id="delivery_address" name="delivery_address" rows="3">{{ old('delivery_address') }}</textarea>
                @error('delivery_address') <p class="alert error">{{ $message }}</p> @enderror
            </div>

            <div style="margin-top: 10px;">
                <label for="customer_notes">Примітки до доставки</label>
                <textarea id="customer_notes" name="customer_notes" rows="2">{{ old('customer_notes') }}</textarea>
                @error('customer_notes') <p class="alert error">{{ $message }}</p> @enderror
            </div>

            <div style="margin-top: 10px;">
                <label for="comment">Коментар до замовлення</label>
                <textarea id="comment" name="comment" rows="4">{{ old('comment') }}</textarea>
                @error('comment') <p class="alert error">{{ $message }}</p> @enderror
            </div>

            <div style="margin-top: 12px;">
                <button class="btn btn-buy" type="submit">Підтвердити замовлення</button>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            (function () {
                var sel = document.getElementById('delivery_type');
                var np = document.getElementById('delivery-np-fields');
                var cr = document.getElementById('delivery-courier-field');
                function sync() {
                    var v = sel && sel.value;
                    if (np) np.style.display = v === 'nova_poshta' ? '' : 'none';
                    if (cr) cr.style.display = v === 'courier' ? '' : 'none';
                }
                if (sel) {
                    sel.addEventListener('change', sync);
                    sync();
                }
            })();
        </script>
    @endpush
@endsection
