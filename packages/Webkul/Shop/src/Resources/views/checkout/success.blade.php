<x-shop::layouts
	:has-header="true"
	:has-feature="false"
	:has-footer="true"
>
    <!-- Page Title -->
    <x-slot:title>
		@lang('shop::app.checkout.success.thanks')
    </x-slot>

	<!-- Page content -->
	<div class="container mt-8 px-[60px] max-lg:px-8">
		<div class="grid place-items-center gap-y-5 max-md:gap-y-2.5">
			{{ view_render_event('bagisto.shop.checkout.success.image.before', ['order' => $order]) }}

			<img
				class="max-md:h-[100px] max-md:w-[100px]"
				src="{{ bagisto_asset('images/thank-you.png') }}"
				alt="@lang('shop::app.checkout.success.thanks')"
				title="@lang('shop::app.checkout.success.thanks')"
                loading="lazy"
                decoding="async"
			>

			{{ view_render_event('bagisto.shop.checkout.success.image.after', ['order' => $order]) }}

			<p class="text-xl max-md:text-sm">
				@if (auth()->guard('customer')->user())
					@lang('shop::app.checkout.success.order-id-info', [
						'order_id' => '<a class="text-blue-700" href="'.route('shop.customers.account.orders.view', $order->id).'">'.$order->increment_id.'</a>'
					])
				@else
					@lang('shop::app.checkout.success.order-id-info', ['order_id' => $order->increment_id])
				@endif
			</p>

			<p class="font-medium md:text-2xl">
				@lang('shop::app.checkout.success.thanks')
			</p>

			<p class="text-xl text-zinc-500 max-md:text-center max-md:text-xs">
				@if (! empty($order->checkout_message))
					{!! nl2br($order->checkout_message) !!}
				@else
					@lang('shop::app.checkout.success.info')
				@endif
			</p>

			{{ view_render_event('bagisto.shop.checkout.success.continue-shopping.before', ['order' => $order]) }}

			<a href="{{ route('shop.home.index') }}">
				<div class="w-max cursor-pointer rounded-2xl bg-navyBlue px-11 py-3 text-center text-base font-medium text-white max-md:rounded-lg max-md:px-6 max-md:py-1.5">
             		@lang('shop::app.checkout.cart.index.continue-shopping')
				</div>
			</a>

			{{ view_render_event('bagisto.shop.checkout.success.continue-shopping.after', ['order' => $order]) }}
		</div>
	</div>

    @pushOnce('scripts')
        <script>
            (function () {
                const orderId = {!! json_encode($order->increment_id, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!};
                const storageKey = 'fbq_purchase_fired_' + orderId;

                if (typeof window.fbqTrack !== 'function') {
                    return;
                }

                if (localStorage.getItem(storageKey)) {
                    return;
                }

                const contents = [
                    @foreach ($order->items as $item)
                        {
                            id: {!! json_encode(optional(optional($item->product)->parent)->sku ?? $item->sku ?? $item->product_id) !!},
                            quantity: {{ $item->qty_ordered }},
                            item_price: {{ (float) $item->price }},
                            content_name: {!! json_encode($item->name ?? optional($item->product)->name ?? '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!},
                            content_category: {!! json_encode(optional(optional($item->product)->categories->first())->name ?? '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!},
                        },
                    @endforeach
                ];

                try {
                    window.fbqTrack('Purchase', {
                        eventID: orderId,
                        value: (float) {{ $order->grand_total }},
                        currency: '{{ core()->getCurrentCurrencyCode() }}',
                        contents: contents,
                        content_ids: contents.map(item => item.id),
                        content_name: {!! json_encode($order->items->first()->name ?? '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!},
                        content_category: {!! json_encode(optional(optional($order->items->first()->product)->categories->first())->name ?? '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!},
                        content_type: 'product',
                        num_items: {{ $order->items->sum('qty_ordered') }},
                    });

                    localStorage.setItem(storageKey, '1');
                } catch (error) {
                    console.warn('fbqPurchaseError', error);
                }
            })();
        </script>
    @endPushOnce
