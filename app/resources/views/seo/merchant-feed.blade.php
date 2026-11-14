{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
    <channel>
        <title>ZOOGLE</title>
        <link>{{ route('home') }}</link>
        <description>{{ __('shop.catalog_meta_description') }}</description>
@foreach ($items as $item)
        <item>
            <g:id>{{ $item['id'] }}</g:id>
            <g:title>{{ $item['title'] }}</g:title>
            <g:description>{{ $item['description'] }}</g:description>
            <g:link>{{ $item['link'] }}</g:link>
            <g:image_link>{{ $item['image'] }}</g:image_link>
            <g:availability>{{ $item['availability'] }}</g:availability>
            <g:price>{{ $item['price'] }}</g:price>
@if ($item['sale_price'] !== null)
            <g:sale_price>{{ $item['sale_price'] }}</g:sale_price>
@endif
            <g:condition>new</g:condition>
            <g:identifier_exists>no</g:identifier_exists>
        </item>
@endforeach
    </channel>
</rss>
