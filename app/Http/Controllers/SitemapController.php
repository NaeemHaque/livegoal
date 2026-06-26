<?php

namespace App\Http\Controllers;

use App\Seo\Slug;
use App\Services\Football\FeaturedMatches;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * Crawl-control surface: a dynamic robots.txt (so the Sitemap line carries an
 * absolute, environment-correct URL) and a sitemap *index* that fans out to
 * child sitemaps — core hub/competition/content pages, plus the high-volume
 * match and team entity pages.
 *
 * Every URL carries a <lastmod>: for a live-scores site that is the decisive
 * recrawl signal, telling Google a result/fixture page just changed so it
 * reindexes within hours instead of on its own slow cadence. Entity sitemaps
 * are built cache-only (FeaturedMatches::all with allowFetch:false) — a crawler
 * fetching the sitemap never reaches the rate-limited upstream API.
 */
class SitemapController extends Controller
{
    public function __construct(private readonly FeaturedMatches $featured) {}

    /**
     * The sitemap index — the single URL submitted to Search Console / Bing.
     */
    public function index(): Response
    {
        $xml = Cache::remember('seo:sitemap:index', 1800, function (): string {
            $now = Carbon::now()->toAtomString();

            return $this->sitemapIndex([
                ['loc' => url('/sitemap-core.xml'), 'lastmod' => $now],
                ['loc' => url('/sitemap-matches.xml'), 'lastmod' => $now],
                ['loc' => url('/sitemap-teams.xml'), 'lastmod' => $now],
                ['loc' => url('/sitemap-news.xml'), 'lastmod' => $now],
            ]);
        });

        return $this->xml($xml);
    }

    /**
     * Evergreen hub, competition and editorial pages. Date pages are listed only
     * when that day actually has fixtures, so we never feed Google a URL the
     * shell renders noindex (a thin, empty date).
     */
    public function core(): Response
    {
        $xml = Cache::remember('seo:sitemap:core', 3600, function (): string {
            $now = Carbon::now()->toAtomString();
            $urls = [];

            foreach (['/', '/matches', '/competitions', '/scorers', '/guides'] as $path) {
                $urls[] = ['loc' => url($path), 'lastmod' => $now];
            }

            foreach (Config::array('football.competitions') as $code) {
                if (is_string($code)) {
                    $urls[] = ['loc' => url('/competition/'.$code), 'lastmod' => $now];
                }
            }

            // Rolling window of date pages (recent results + upcoming fixtures),
            // restricted to days that have fixtures in the cached feeds.
            $agg = $this->featured->all(allowFetch: false);
            $dateLastmod = $agg['lastUpdated'] ?? $now;
            $start = Carbon::now()->subDays(2);

            for ($offset = 0; $offset <= 9; $offset++) {
                $date = $start->copy()->addDays($offset)->toDateString();

                if ($this->featured->onDate($agg['matches'], $date) !== []) {
                    $urls[] = ['loc' => url('/matches/'.$date), 'lastmod' => $dateLastmod];
                }
            }

            // Editorial content pages (guides, explainers, trust).
            foreach (Config::array('guides') as $page) {
                if (is_array($page) && is_string($page['path'] ?? null)) {
                    $urls[] = ['loc' => url($page['path']), 'lastmod' => $now];
                }
            }

            // Per-term glossary pages and per-country "how to watch free" pages.
            foreach (array_keys(Config::array('glossary')) as $slug) {
                if (is_string($slug)) {
                    $urls[] = ['loc' => url('/guides/'.$slug), 'lastmod' => $now];
                }
            }

            foreach (array_keys(Config::array('watch')) as $country) {
                if (is_string($country)) {
                    $urls[] = ['loc' => url('/guides/how-to-watch-world-cup-2026-free/'.$country), 'lastmod' => $now];
                }
            }

            return $this->urlset($urls);
        });

        return $this->xml($xml);
    }

    /**
     * Every cached match (the "[A] vs [B] live score" pages). Short TTL + a
     * feed-derived <lastmod> keeps live results recrawled fast.
     */
    public function matches(): Response
    {
        $xml = Cache::remember('seo:sitemap:matches', 300, function (): string {
            $agg = $this->featured->all(allowFetch: false);
            $lastmod = $agg['lastUpdated'] ?? Carbon::now()->toAtomString();
            $urls = [];

            foreach ($agg['matches'] as $match) {
                $id = $this->str($match['id'] ?? null);
                $home = $this->str(data_get($match, 'home.name'));
                $away = $this->str(data_get($match, 'away.name'));

                if ($id === '' || $home === '' || $away === '') {
                    continue;
                }

                $urls[] = ['loc' => Slug::url('match', $id, "{$home} vs {$away}"), 'lastmod' => $lastmod];
            }

            return $this->urlset($this->dedupe($urls));
        });

        return $this->xml($xml);
    }

    /**
     * Every team currently appearing in a cached feed (World Cup nations + the
     * featured leagues' clubs). Cache-only, so the set is exactly what we hold.
     */
    public function teams(): Response
    {
        $xml = Cache::remember('seo:sitemap:teams', 300, function (): string {
            $agg = $this->featured->all(allowFetch: false);
            $lastmod = $agg['lastUpdated'] ?? Carbon::now()->toAtomString();
            $seen = [];
            $urls = [];

            foreach ($agg['matches'] as $match) {
                foreach (['home', 'away'] as $side) {
                    $id = $this->str(data_get($match, "{$side}.id"));
                    $name = $this->str(data_get($match, "{$side}.name"));

                    if ($id === '' || $name === '' || isset($seen[$id])) {
                        continue;
                    }

                    $seen[$id] = true;
                    $urls[] = ['loc' => Slug::url('team', $id, $name), 'lastmod' => $lastmod];
                }
            }

            return $this->urlset($urls);
        });

        return $this->xml($xml);
    }

    /**
     * Google News sitemap (Top Stories / Discover): just-finished matches, the
     * fresh "result" pages most likely to surface during a live tournament.
     * Google News only considers URLs published in the last 48 hours, so the
     * list is intentionally short and time-boxed.
     */
    public function news(): Response
    {
        $xml = Cache::remember('seo:sitemap:news', 300, function (): string {
            $agg = $this->featured->all(allowFetch: false);

            return $this->newsUrlset($this->recentResults($agg['matches']));
        });

        return $this->xml($xml);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /scheduler/',
            'Disallow: /api/',
            'Disallow: /settings',
            'Disallow: /favorites',
            'Disallow: /search',
            'Disallow: /up',
            '',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Finished matches that kicked off within the last 48 hours, newest first —
     * the Google News eligibility window.
     *
     * @param  list<array<string, mixed>>  $matches
     * @return list<array{loc: string, title: string, date: string}>
     */
    private function recentResults(array $matches): array
    {
        $cutoff = Carbon::now()->subDays(2);
        $items = [];

        foreach ($matches as $match) {
            if (($match['status'] ?? null) !== 'FT') {
                continue;
            }

            $kickoff = $match['kickoff'] ?? null;

            if (! is_string($kickoff)) {
                continue;
            }

            try {
                $when = Carbon::parse($kickoff);
            } catch (\Throwable) {
                continue;
            }

            if ($when->lt($cutoff)) {
                continue;
            }

            $id = $this->str($match['id'] ?? null);
            $home = $this->str(data_get($match, 'home.name'));
            $away = $this->str(data_get($match, 'away.name'));

            if ($id === '' || $home === '' || $away === '') {
                continue;
            }

            $home_score = $this->str($match['homeScore'] ?? null) ?: '0';
            $away_score = $this->str($match['awayScore'] ?? null) ?: '0';
            $competition = $this->str(data_get($match, 'competition.name'));

            $title = "Full time: {$home} {$home_score}–{$away_score} {$away}";

            if ($competition !== '') {
                $title .= " — {$competition}";
            }

            $items[] = [
                'loc' => Slug::url('match', $id, "{$home} vs {$away}"),
                'title' => $title,
                'date' => $when->toAtomString(),
            ];
        }

        usort($items, fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        return $items;
    }

    /**
     * Drop duplicate <loc>s (a match can appear in more than one cached feed).
     *
     * @param  list<array{loc: string, lastmod: string}>  $urls
     * @return list<array{loc: string, lastmod: string}>
     */
    private function dedupe(array $urls): array
    {
        $seen = [];
        $out = [];

        foreach ($urls as $url) {
            if (isset($seen[$url['loc']])) {
                continue;
            }

            $seen[$url['loc']] = true;
            $out[] = $url;
        }

        return $out;
    }

    /**
     * @param  list<array{loc: string, lastmod?: string}>  $urls
     */
    private function urlset(array $urls): string
    {
        $body = '';

        foreach ($urls as $url) {
            $body .= '<url><loc>'.htmlspecialchars($url['loc'], ENT_XML1).'</loc>';

            if (isset($url['lastmod'])) {
                $body .= '<lastmod>'.htmlspecialchars($url['lastmod'], ENT_XML1).'</lastmod>';
            }

            $body .= '</url>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$body.'</urlset>';
    }

    /**
     * @param  list<array{loc: string, title: string, date: string}>  $items
     */
    private function newsUrlset(array $items): string
    {
        $publication = Config::string('seo.news_publication');
        $language = Config::string('seo.news_language');
        $body = '';

        foreach ($items as $item) {
            $body .= '<url><loc>'.htmlspecialchars($item['loc'], ENT_XML1).'</loc>'
                .'<news:news>'
                .'<news:publication>'
                .'<news:name>'.htmlspecialchars($publication, ENT_XML1).'</news:name>'
                .'<news:language>'.htmlspecialchars($language, ENT_XML1).'</news:language>'
                .'</news:publication>'
                .'<news:publication_date>'.htmlspecialchars($item['date'], ENT_XML1).'</news:publication_date>'
                .'<news:title>'.htmlspecialchars($item['title'], ENT_XML1).'</news:title>'
                .'</news:news></url>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '
            .'xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">'.$body.'</urlset>';
    }

    /**
     * @param  list<array{loc: string, lastmod: string}>  $maps
     */
    private function sitemapIndex(array $maps): string
    {
        $body = '';

        foreach ($maps as $map) {
            $body .= '<sitemap><loc>'.htmlspecialchars($map['loc'], ENT_XML1).'</loc>'
                .'<lastmod>'.htmlspecialchars($map['lastmod'], ENT_XML1).'</lastmod></sitemap>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$body.'</sitemapindex>';
    }

    private function xml(string $xml): Response
    {
        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    private function str(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
