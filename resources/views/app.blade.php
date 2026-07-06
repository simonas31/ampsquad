<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    {{--
        These are rendered server-side (not just via the client-side <Seo>
        component) because crawlers and social-media unfurl bots request a
        URL directly and never execute the JS that would otherwise fill
        them in — this app has no SSR, so the very first response is the
        only one they ever see. Every tag below (other than <title>, which
        Inertia already reconciles by itself) is marked data-seo="server"
        and gets stripped by app.ts right before Vue mounts, so real
        browser sessions hand off cleanly to Inertia's own head management
        without ending up with duplicate tags.
    --}}
    @php $seo = $page['props']['seo'] ?? null; @endphp
    @if ($seo)
        <title inertia>{{ $seo['title'] }}</title>
        @if (! empty($seo['description']))
            <meta name="description" content="{{ $seo['description'] }}" data-seo="server" />
        @endif
        @if (! empty($seo['noindex']))
            <meta name="robots" content="noindex, nofollow" data-seo="server" />
        @endif
        @if (! empty($seo['canonical']))
            <link rel="canonical" href="{{ $seo['canonical'] }}" data-seo="server" />
        @endif
        @foreach ($seo['alternates'] ?? [] as $alternate)
            <link rel="alternate" hreflang="{{ $alternate['locale'] }}" href="{{ $alternate['url'] }}" data-seo="server" />
        @endforeach
        <meta property="og:title" content="{{ $seo['title'] }}" data-seo="server" />
        @if (! empty($seo['description']))
            <meta property="og:description" content="{{ $seo['description'] }}" data-seo="server" />
        @endif
        <meta property="og:type" content="{{ $seo['ogType'] }}" data-seo="server" />
        @if (! empty($seo['canonical']))
            <meta property="og:url" content="{{ $seo['canonical'] }}" data-seo="server" />
        @endif
        @if (! empty($seo['ogImage']))
            <meta property="og:image" content="{{ $seo['ogImage'] }}" data-seo="server" />
        @endif
        <meta name="twitter:card" content="{{ ! empty($seo['ogImage']) ? 'summary_large_image' : 'summary' }}" data-seo="server" />
        <meta name="twitter:title" content="{{ $seo['title'] }}" data-seo="server" />
        @if (! empty($seo['description']))
            <meta name="twitter:description" content="{{ $seo['description'] }}" data-seo="server" />
        @endif
        @if (! empty($seo['ogImage']))
            <meta name="twitter:image" content="{{ $seo['ogImage'] }}" data-seo="server" />
        @endif
        @foreach ($seo['jsonLd'] ?? [] as $schema)
            <script type="application/ld+json" data-seo="server">{!! json_encode($schema, JSON_UNESCAPED_UNICODE) !!}</script>
        @endforeach
    @else
        <title inertia>{{ config('app.name', 'Laravel') }}</title>
    @endif

    @vite('resources/js/app.ts')
    @inertiaHead
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>
