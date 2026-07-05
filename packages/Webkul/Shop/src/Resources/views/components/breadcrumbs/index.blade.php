@php
    use Diglactic\Breadcrumbs\Breadcrumbs;

    $breadcrumbs = Breadcrumbs::generate($name, $entity);
@endphp

@props([
    'name'  => '',
    'entity' => null,
])

<div class="mt-[34px] flex justify-start max-lg:hidden">
    <div class="flex items-center gap-x-3.5">
        @include('shop::partials.breadcrumbs', ['breadcrumbs' => $breadcrumbs])
    </div>
</div>

@if ($breadcrumbs->isNotEmpty())
    @php
        $breadcrumbItems = $breadcrumbs->values()->map(function ($breadcrumb, $index) {
            return [
                '@type'    => 'ListItem',
                'position' => $index + 1,
                'name'     => strip_tags($breadcrumb->title),
                'item'     => $breadcrumb->url ?: url()->current(),
            ];
        })->all();

        $breadcrumbJsonLd = [
            '@context' => 'https://schema.org',
            '@type'    => 'BreadcrumbList',
            'itemListElement' => $breadcrumbItems,
        ];
    @endphp

    <script type="application/ld+json">
        {!! json_encode($breadcrumbJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endif
