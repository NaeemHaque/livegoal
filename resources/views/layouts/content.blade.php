<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    @include('partials.seo-head')

    @vite(['resources/css/app.css'])

    <style>
        .lg-doc-bar {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
            max-width: 880px;
            margin: 0 auto;
            padding: 18px 20px;
        }
        .lg-doc-logo { font-family: var(--font-display); font-weight: 800; font-size: 20px; color: var(--text); letter-spacing: -0.01em; }
        .lg-doc-logo .a { color: var(--accent); }
        .lg-doc-bar nav { display: flex; gap: 14px; flex-wrap: wrap; font-size: 14px; }
        .lg-doc-bar nav a { color: var(--text-muted); }
        .lg-doc-bar nav a:hover { color: var(--text); }

        .lg-doc { max-width: 720px; margin: 0 auto; padding: 8px 20px 64px; }
        .lg-crumbs { font-size: 13px; color: var(--text-muted); margin-bottom: 22px; }
        .lg-crumbs a { color: var(--text-muted); }
        .lg-crumbs a:hover { color: var(--accent); }

        .lg-prose { color: var(--text-2); font-family: var(--font-body); font-size: 16.5px; line-height: 1.7; }
        .lg-prose h1 { font-family: var(--font-display); font-weight: 800; font-size: clamp(28px, 5vw, 40px); line-height: 1.12; color: var(--text); letter-spacing: -0.015em; margin: 0 0 8px; }
        .lg-prose .lede { font-size: 18px; color: var(--text); margin: 0 0 28px; }
        .lg-prose h2 { font-family: var(--font-display); font-weight: 700; font-size: 23px; color: var(--text); margin: 38px 0 12px; }
        .lg-prose h3 { font-weight: 700; font-size: 18px; color: var(--text); margin: 24px 0 8px; }
        .lg-prose p { margin: 0 0 16px; }
        .lg-prose a { color: var(--accent); text-decoration: underline; text-underline-offset: 2px; }
        .lg-prose ul, .lg-prose ol { margin: 0 0 18px; padding-left: 22px; }
        .lg-prose li { margin: 0 0 7px; }
        .lg-prose strong { color: var(--text); }
        .lg-prose table { width: 100%; border-collapse: collapse; margin: 0 0 22px; font-size: 15px; }
        .lg-prose th, .lg-prose td { text-align: left; padding: 9px 10px; border-bottom: 1px solid var(--border); vertical-align: top; }
        .lg-prose th { color: var(--text); font-weight: 700; }
        .lg-prose .note { font-size: 14px; color: var(--text-muted); border-left: 2px solid var(--border-strong); padding: 4px 0 4px 14px; margin: 0 0 18px; }

        .lg-doc-foot { border-top: 1px solid var(--border); margin-top: 40px; }
        .lg-doc-foot .in { max-width: 880px; margin: 0 auto; padding: 28px 20px 48px; display: grid; gap: 22px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
        .lg-doc-foot h4 { font-size: 12px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-faint); margin: 0 0 10px; }
        .lg-doc-foot a { display: block; color: var(--text-muted); font-size: 14px; margin-bottom: 7px; }
        .lg-doc-foot a:hover { color: var(--text); }
    </style>
</head>
<body>
    <header class="lg-doc-bar">
        <a class="lg-doc-logo" href="{{ url('/') }}">Live<span class="a">Goal</span></a>
        <nav>
            <a href="{{ url('/') }}">Live</a>
            <a href="{{ url('/matches') }}">Matches</a>
            <a href="{{ url('/competitions') }}">Competitions</a>
            <a href="{{ url('/scorers') }}">Top scorers</a>
            <a href="{{ url('/guides') }}">Guides</a>
        </nav>
    </header>

    <main class="lg-doc">
        @isset($page)
            <nav class="lg-crumbs" aria-label="Breadcrumb">
                <a href="{{ url('/') }}">Home</a> ›
                @if (\Illuminate\Support\Str::startsWith($page['path'], '/guides/'))
                    <a href="{{ url('/guides') }}">Guides</a> ›
                @endif
                <span>{{ $page['title'] }}</span>
            </nav>
        @endisset

        <article class="lg-prose">
            @yield('content')
        </article>
    </main>

    <footer class="lg-doc-foot">
        <div class="in">
            <div>
                <h4>World Cup 2026</h4>
                @foreach (config('guides') as $slug => $guide)
                    @if (($guide['group'] ?? '') === 'World Cup 2026')
                        <a href="{{ url($guide['path']) }}">{{ $guide['nav'] }}</a>
                    @endif
                @endforeach
            </div>
            <div>
                <h4>Scores</h4>
                <a href="{{ url('/') }}">Live scores</a>
                <a href="{{ url('/matches') }}">Fixtures &amp; results</a>
                <a href="{{ url('/competitions') }}">Competitions</a>
                <a href="{{ url('/scorers') }}">Top scorers</a>
            </div>
            <div>
                <h4>LiveGoal</h4>
                <a href="{{ url('/guides/football-glossary') }}">Football glossary</a>
                <a href="{{ url('/about') }}">About</a>
                <a href="{{ url('/how-our-data-works') }}">How our data works</a>
                <a href="{{ url('/contact') }}">Contact</a>
            </div>
        </div>
    </footer>
</body>
</html>
