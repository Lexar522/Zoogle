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
@foreach ($item['additional_images'] as $additionalImage)
            <g:additional_image_link>{{ $additionalImage }}</g:additional_image_link>
@endforeach
            <g:google_product_category>{{ $item['google_product_category'] }}</g:google_product_category>
@if ($item['product_type'] !== null)
            <g:product_type>{{ $item['product_type'] }}</g:product_type>
@endif
            <g:availability>{{ $item['availability'] }}</g:availability>
            <g:price>{{ $item['price'] }}</g:price>
@if ($item['sale_price'] !== null)
            <g:sale_price>{{ $item['sale_price'] }}</g:sale_price>
@endif
            <g:brand>{{ \App\Services\GoogleMerchantFeed::BRAND }}</g:brand>
            <g:condition>new</g:condition>
            <g:identifier_exists>no</g:identifier_exists>
        </item>
@endforeach
    </channel>
</rss>
