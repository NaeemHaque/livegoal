@php
    // Per-URL SEO metadata is injected by the controller (SeoShellController /
    // ContentController). The fallback keeps a view renderable without it.
    $seo ??= new \App\Seo\SeoMeta(config('seo.default_title'), config('seo.default_description'), url()->current());
    $ogImage = config('seo.og_image');
    $ogImage = $ogImage ? (\Illuminate\Support\Str::startsWith($ogImage, 'http') ? $ogImage : url($ogImage)) : null;
@endphp

<title>{{ $seo->title }}</title>
<meta name="description" content="{{ $seo->description }}">
<meta name="robots" content="{{ $seo->robots }}">
<link rel="canonical" href="{{ $seo->canonical }}">

{{-- Open Graph / Twitter so shared links (WhatsApp, X, Discord, Slack) render a preview. --}}
<meta property="og:site_name" content="{{ config('seo.site_name') }}">
<meta property="og:type" content="{{ $seo->ogType }}">
<meta property="og:title" content="{{ $seo->title }}">
<meta property="og:description" content="{{ $seo->description }}">
<meta property="og:url" content="{{ $seo->canonical }}">
<meta property="og:locale" content="{{ config('seo.locale') }}">
@if ($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
@endif
<meta name="twitter:card" content="{{ config('seo.og_image_wide') ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $seo->title }}">
<meta name="twitter:description" content="{{ $seo->description }}">
@if ($ogImage)
    <meta name="twitter:image" content="{{ $ogImage }}">
@endif
@if ($handle = config('seo.twitter'))
    <meta name="twitter:site" content="{{ $handle }}">
@endif

{{-- Structured data (Organization/WebSite site-wide; SportsEvent/SportsTeam/
     Person/SportsOrganization/BreadcrumbList on detail and content pages). --}}
@foreach ($seo->jsonLdScripts() as $jsonLd)
    <script type="application/ld+json">{!! $jsonLd !!}</script>
@endforeach

{{-- Set the theme before first paint to avoid a flash of the wrong theme. --}}
<script>
    (function () {
        try {
            var t = localStorage.getItem('pp_theme')
                || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', t);
        } catch (e) {}
    })();
</script>

<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#0A0D12">
{{-- iOS web push requires the site installed to the Home Screen (16.4+). --}}
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="LiveGoal">

{{-- Crests/emblems are loaded from here on nearly every page. Fonts are
     self-hosted (bundled via @fontsource in app.css), so there's no
     render-blocking request to Google Fonts. --}}
<link rel="preconnect" href="https://crests.football-data.org" crossorigin>

{{-- Privacy-friendly, cookieless analytics. Renders only when configured (PLAUSIBLE_DOMAIN). --}}
@if ($plausibleDomain = config('services.plausible.domain'))
    <script defer data-domain="{{ $plausibleDomain }}" src="{{ config('services.plausible.src') }}"></script>
@endif
