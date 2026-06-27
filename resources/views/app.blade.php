<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    @include('partials.seo-head')

    {{-- Web Push: the VAPID public key the browser subscribes with (see docs/PUSH_NOTIFICATIONS.md). --}}
    <meta name="vapid-public-key" content="{{ (string) config('webpush.vapid.public_key') }}">

    @vite(['resources/css/app.css', 'resources/js/main.js'])

    {{-- Full-page boot loader ("Formation build"). Paints instantly (inline,
         before the JS bundle runs) so there's no layout shift, then main.js
         fades it out once the app has mounted. Theme-aware via CSS variables
         (data-theme is already set above), with dark fallbacks. --}}
    <style>
        #pp-loader {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 26px;
            background:
                radial-gradient(120% 80% at 50% 6%, color-mix(in srgb, var(--accent, #C6FF3A) 10%, transparent), transparent 52%),
                radial-gradient(140% 120% at 50% 120%, color-mix(in srgb, var(--cyan, #00E5FF) 6%, transparent), transparent 58%),
                color-mix(in srgb, var(--bg-base, #0A0D12) 72%, transparent);
            -webkit-backdrop-filter: blur(14px) saturate(125%);
            backdrop-filter: blur(14px) saturate(125%);
            font-family: 'Saira Condensed', 'Arial Narrow', system-ui, sans-serif;
            transition:
                opacity 0.7s ease,
                visibility 0.7s ease,
                transform 0.7s ease,
                -webkit-backdrop-filter 0.7s ease,
                backdrop-filter 0.7s ease;
        }
        /* Exit as a clearing frosted glass: the page is held behind the blur,
           which lifts (blur → 0) as the loader fades and gently scales away, so
           the page sharpens into focus instead of snapping in. */
        #pp-loader.pp-loader-hide {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transform: scale(1.04);
            -webkit-backdrop-filter: blur(0);
            backdrop-filter: blur(0);
        }

        #pp-loader .ld-logo { display: flex; align-items: center; gap: 11px; animation: ld-fade 0.5s ease both; }
        #pp-loader .ld-ball { width: 40px; height: 40px; flex: none; overflow: visible; }
        #pp-loader .ld-ball .body { fill: var(--text, #E6EAF0); }
        #pp-loader .ld-ball .panel { fill: var(--bg-base, #0A0D12); }
        #pp-loader .ld-ball .seam { stroke: var(--bg-base, #0A0D12); }
        #pp-loader .ld-word { display: flex; flex-direction: column; line-height: 1; align-items: flex-start; }
        #pp-loader .ld-word b { font-size: 25px; font-weight: 800; letter-spacing: -0.01em; color: var(--text, #E6EAF0); }
        #pp-loader .ld-word b .a { color: var(--accent, #C6FF3A); }
        #pp-loader .ld-word small { font-size: 9.5px; font-weight: 700; letter-spacing: 0.22em; color: var(--text-muted, #8A93A3); text-transform: uppercase; margin-top: 3px; }

        #pp-loader .ld-pitch {
            position: relative;
            width: 264px;
            height: 340px;
            border: 1.5px solid color-mix(in srgb, var(--text, #FFFFFF) 12%, transparent);
            border-radius: 10px;
            background: repeating-linear-gradient(0deg, color-mix(in srgb, var(--pitch, #21C17A) 6%, transparent) 0 28px, color-mix(in srgb, var(--pitch, #21C17A) 2%, transparent) 28px 56px);
            overflow: hidden;
            animation: ld-fade 0.5s ease both;
        }
        #pp-loader .ld-pitch .half { position: absolute; left: 0; right: 0; top: 50%; height: 1.5px; background: color-mix(in srgb, var(--text, #FFFFFF) 10%, transparent); }
        #pp-loader .ld-pitch .circle { position: absolute; left: 50%; top: 50%; width: 92px; height: 92px; transform: translate(-50%, -50%); border: 1.5px solid color-mix(in srgb, var(--text, #FFFFFF) 10%, transparent); border-radius: 50%; }
        #pp-loader .ld-pitch .spot { position: absolute; left: 50%; top: 50%; width: 5px; height: 5px; transform: translate(-50%, -50%); background: color-mix(in srgb, var(--text, #FFFFFF) 18%, transparent); border-radius: 50%; }
        #pp-loader .ld-pitch .box { position: absolute; left: 50%; transform: translateX(-50%); width: 120px; height: 46px; border: 1.5px solid color-mix(in srgb, var(--text, #FFFFFF) 10%, transparent); }
        #pp-loader .ld-pitch .box.top { top: -1.5px; border-top: none; border-radius: 0 0 6px 6px; }
        #pp-loader .ld-pitch .box.bot { bottom: -1.5px; border-bottom: none; border-radius: 6px 6px 0 0; }

        #pp-loader .ld-dot {
            position: absolute;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--accent, #C6FF3A);
            transform: translate(-50%, -50%) scale(0.2);
            box-shadow: 0 0 12px -2px color-mix(in srgb, var(--accent, #C6FF3A) 60%, transparent), inset 0 0 0 2px rgba(10, 13, 18, 0.18);
            opacity: 0;
            animation: ld-pop 2.8s ease-in-out infinite;
        }
        #pp-loader .ld-dot::after { content: attr(data-n); position: absolute; inset: 0; display: grid; place-items: center; font-size: 9px; font-weight: 800; color: var(--accent-text, #0A0D12); font-family: 'Saira Condensed', sans-serif; }
        @keyframes ld-pop {
            0%, 6% { opacity: 0; transform: translate(-50%, -50%) scale(0.2); }
            16% { opacity: 1; transform: translate(-50%, -50%) scale(1.18); }
            22% { transform: translate(-50%, -50%) scale(1); }
            78% { opacity: 1; transform: translate(-50%, -50%) scale(1); }
            90%, 100% { opacity: 0; transform: translate(-50%, -50%) scale(0.2); }
        }

        #pp-loader .ld-foot { display: flex; flex-direction: column; align-items: center; gap: 14px; animation: ld-fade 0.5s ease both; }
        #pp-loader .ld-bar { width: 172px; height: 5px; border-radius: 999px; background: var(--surface-2, #1A212C); overflow: hidden; position: relative; }
        #pp-loader .ld-bar::before { content: ''; position: absolute; top: 0; bottom: 0; width: 42%; border-radius: 999px; background: linear-gradient(90deg, transparent, var(--accent, #C6FF3A), var(--cyan, #00E5FF)); animation: ld-bar 1.4s cubic-bezier(0.16, 1, 0.3, 1) infinite; }
        @keyframes ld-bar { 0% { left: -42%; } 100% { left: 100%; } }
        #pp-loader .ld-status { font-size: 11.5px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--text-muted, #8A93A3); animation: ld-txt 1.6s ease-in-out infinite; }
        @keyframes ld-txt { 0%, 100% { opacity: 0.5; } 50% { opacity: 1; } }
        @keyframes ld-fade { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }

        @media (max-width: 480px) { #pp-loader .ld-pitch { width: 220px; height: 290px; } }
        @media (prefers-reduced-motion: reduce) {
            #pp-loader .ld-dot { animation: none; opacity: 1; transform: translate(-50%, -50%) scale(1); }
            #pp-loader .ld-bar::before { animation: none; left: 0; width: 66%; }
            #pp-loader .ld-status { animation: none; opacity: 0.85; }
        }
    </style>
</head>
<body>
    <div id="pp-loader" role="status" aria-label="Loading LiveGoal">
        <div class="ld-logo">
            <svg class="ld-ball" viewBox="0 0 48 48" width="40" height="40" fill="none" aria-hidden="true">
                <circle class="body" cx="22" cy="24" r="15" />
                <polygon class="panel" points="22,16 29,21 26.5,29.5 17.5,29.5 15,21" />
                <g class="seam" stroke-width="1.5" fill="none" stroke-linecap="round">
                    <path d="M22 16V9M29 21l6.3-2.3M26.5 29.5l4.4 5.5M17.5 29.5l-4.4 5.5M15 21l-6.3-2.3" />
                </g>
                <circle class="seam" cx="22" cy="24" r="15" fill="none" stroke-width="1.6" />
                <circle cx="39" cy="11" r="5" fill="#FF3D3D" />
            </svg>
            <span class="ld-word"><b>Live<span class="a">Goal</span></b></span>
        </div>

        <div class="ld-pitch">
            <span class="box top"></span><span class="box bot"></span>
            <span class="half"></span><span class="circle"></span><span class="spot"></span>
            <span class="ld-dot" data-n="1" style="left: 50%; top: 88%; animation-delay: 0s"></span>
            <span class="ld-dot" data-n="2" style="left: 16%; top: 70%; animation-delay: 0.12s"></span>
            <span class="ld-dot" data-n="5" style="left: 38%; top: 66%; animation-delay: 0.24s"></span>
            <span class="ld-dot" data-n="15" style="left: 62%; top: 66%; animation-delay: 0.36s"></span>
            <span class="ld-dot" data-n="3" style="left: 84%; top: 70%; animation-delay: 0.48s"></span>
            <span class="ld-dot" data-n="8" style="left: 28%; top: 46%; animation-delay: 0.6s"></span>
            <span class="ld-dot" data-n="6" style="left: 50%; top: 42%; animation-delay: 0.72s"></span>
            <span class="ld-dot" data-n="14" style="left: 72%; top: 46%; animation-delay: 0.84s"></span>
            <span class="ld-dot" data-n="11" style="left: 22%; top: 22%; animation-delay: 0.96s"></span>
            <span class="ld-dot" data-n="9" style="left: 50%; top: 17%; animation-delay: 1.08s"></span>
            <span class="ld-dot" data-n="7" style="left: 78%; top: 22%; animation-delay: 1.2s"></span>
        </div>

        <div class="ld-foot">
            <div class="ld-bar"></div>
            <div class="ld-status">Setting up the lineup</div>
        </div>
    </div>

    {{-- Crawlable navigation for clients that don't run JavaScript (some search
         and AI crawlers): a real link graph into the key sections and entities,
         plus a one-line description of what LiveGoal is. --}}
    <noscript>
        <nav aria-label="LiveGoal sections">
            <a href="{{ url('/') }}">Live scores</a>
            <a href="{{ url('/matches') }}">Fixtures &amp; results</a>
            <a href="{{ url('/competitions') }}">Competitions</a>
            <a href="{{ url('/scorers') }}">Top scorers</a>
            <a href="{{ url('/guides') }}">Guides</a>
            @foreach (config('football.featured') as $code)
                @php($competitionMeta = config('football.meta.'.$code))
                @if ($competitionMeta)
                    <a href="{{ url('/competition/'.$code) }}">{{ $competitionMeta['short'] ?? $code }}</a>
                @endif
            @endforeach
        </nav>
        <p>LiveGoal shows free, real-time football scores, fixtures, results, standings and
            knockout brackets for the FIFA World Cup 2026 and major leagues — no betting ads.
            New here? Read <a href="{{ url('/guides/world-cup-2026-format-explained') }}">how the
            World Cup 2026 format works</a> or <a href="{{ url('/about') }}">about LiveGoal</a>.</p>
    </noscript>

    {{-- Server-rendered facts for crawlers/AI bots that don't run JavaScript.
         Vue replaces #app on mount, so users get the live SPA — same data, so
         this is content parity (SSR-style), not cloaking. --}}
    <div id="app">@isset($prerender)@include($prerender['view'], $prerender['data'])@endisset</div>
</body>
</html>
