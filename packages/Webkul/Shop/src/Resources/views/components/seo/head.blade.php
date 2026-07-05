@php
    $currentChannel = core()->getCurrentChannel();
    $siteName = config('app.name') ?: 'Dragoon';

    $defaultTitle = trim($title ?? $siteName);
    $defaultDescription = trim($currentChannel->home_seo['meta_description'] ?? '');
    $defaultImage = $currentChannel->logo_url ?? $currentChannel->favicon_url ?? url('/favicon.ico');
    $currentLocaleCode = app()->getLocale();

    $seoTitle = trim($seoTitle ?? $title ?? $defaultTitle);
    $seoDescription = trim($seoDescription ?? $defaultDescription);
    $canonicalUrl = trim($seoCanonical ?? url()->current());
    $seoType = trim($seoType ?? 'website');
    $seoImage = trim($seoImage ?? $defaultImage);
    $twitterImage = trim($seoTwitterImage ?? $seoImage);

    $ogLocale = match ($currentLocaleCode) {
        'ar' => 'ar_EG',
        'en' => 'en_US',
        default => strtoupper(str_replace('-', '_', $currentLocaleCode)),
    };

    $siteSchema = [
        '@context' => 'https://schema.org',
        '@graph'   => [
            [
                '@type' => 'Organization',
                '@id'   => url('/#organization'),
                'name'  => $siteName,
                'url'   => url('/'),
                'logo'  => $defaultImage,
            ],
            [
                '@type'           => 'WebSite',
                '@id'             => url('/#website'),
                'url'             => url('/'),
                'name'            => $siteName,
                'publisher'       => [
                    '@id' => url('/#organization'),
                ],
                'potentialAction' => [
                    '@type'        => 'SearchAction',
                    'target'       => url('/search/?term={search_term_string}'),
                    'query-input'  => 'required name=search_term_string',
                ],
            ],
        ],
    ];
@endphp

<link rel="canonical" href="{{ $canonicalUrl }}" />

<meta property="og:type" content="{{ $seoType }}" />
<meta property="og:title" content="{{ $seoTitle }}" />
<meta property="og:description" content="{{ $seoDescription }}" />
<meta property="og:url" content="{{ $canonicalUrl }}" />
<meta property="og:image" content="{{ $seoImage }}" />
<meta property="og:site_name" content="{{ $siteName }}" />
<meta property="og:locale" content="{{ $ogLocale }}" />

<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="{{ $seoTitle }}" />
<meta name="twitter:description" content="{{ $seoDescription }}" />
<meta name="twitter:image" content="{{ $twitterImage }}" />
<meta name="twitter:url" content="{{ $canonicalUrl }}" />

<script type="application/ld+json">
{!! json_encode($siteSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
