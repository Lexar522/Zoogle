@php
    $fieldWrapperView = $getFieldWrapperView();
    $html = $html ?? null;
@endphp

<x-dynamic-component :component="$fieldWrapperView" :field="$field">
    <div class="fi-integration-status w-full max-w-none py-1 not-prose [&_svg]:h-6 [&_svg]:w-6 [&_svg]:max-h-6 [&_svg]:max-w-6 [&_svg]:shrink-0">
        @if ($html instanceof \Illuminate\Contracts\Support\Htmlable)
            {!! $html->toHtml() !!}
        @elseif (filled($html))
            {!! $html !!}
        @endif
    </div>
</x-dynamic-component>
