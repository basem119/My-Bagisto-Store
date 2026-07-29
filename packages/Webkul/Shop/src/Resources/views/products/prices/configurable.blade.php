<p class="price-label text-sm text-zinc-500 max-sm:text-xs max-sm:hidden">
    @lang('shop::app.products.prices.configurable.as-low-as')
</p>

@if (isset($prices['final']))
    <p class="regular-price text-sm text-gray-400 line-through max-sm:text-xs">
        {{ $prices['regular']['formatted_price'] }}
    </p>

    <p class="final-price text-lg font-semibold max-sm:text-sm">
        {{ $prices['final']['formatted_price'] }}
    </p>
@else
    <p class="final-price text-lg font-semibold max-sm:text-sm">
        {{ $prices['regular']['formatted_price'] }}
    </p>
@endif