@php
    $depth = $depth ?? 0;
    $nodeId = (int) ($node['id'] ?? 0);
    $children = is_array($node['children'] ?? null) ? $node['children'] : [];
    $hasChildren = $children !== [];
    $isActive = $nodeId > 0 && $nodeId === (int) ($categoryValueId ?? 0);
    $isOpen = in_array($nodeId, $previewOpenCategoryIds ?? [], true);
    $name = mt((string) ($node['name'] ?? ''));
@endphp
<li class="side-nav__item @if ($isOpen) is-open @endif @if ($isActive) is-active @endif" data-depth="{{ $depth }}">
    <div class="side-nav__row">
        <a class="side-nav__link" href="{{ route('preview.catalog-sidebar', ['category' => $nodeId]) }}">
            <span class="side-nav__label">{{ $name }}</span>
        </a>
        @if ($hasChildren)
            <button
                type="button"
                class="side-nav__expand"
                aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                aria-label="{{ $isOpen ? 'Згорнути' : 'Розгорнути' }} {{ $name }}"
            >
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                    <path d="M7 10l5 5 5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        @endif
    </div>
    @if ($hasChildren)
        <ul class="side-nav__sub" role="list">
            @foreach ($children as $child)
                @include('preview.partials.sidebar-node', [
                    'node' => $child,
                    'depth' => $depth + 1,
                    'categoryValueId' => $categoryValueId ?? 0,
                    'previewOpenCategoryIds' => $previewOpenCategoryIds ?? [],
                ])
            @endforeach
        </ul>
    @endif
</li>
