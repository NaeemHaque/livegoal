<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * Crawl-control surface: a dynamic robots.txt (so the Sitemap line carries an
 * absolute, environment-correct URL) and an XML sitemap of the indexable hub and
 * competition pages. Neither touches the upstream API — the competition list is
 * the static free-tier set from config.
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $xml = Cache::remember('seo:sitemap', 3600, function (): string {
            $urls = [url('/'), url('/matches'), url('/competitions'), url('/scorers')];

            foreach (Config::array('football.competitions') as $code) {
                if (is_string($code)) {
                    $urls[] = url('/competition/'.$code);
                }
            }

            // Rolling window of date pages (recent results + upcoming fixtures).
            $start = Carbon::now()->subDays(2);

            for ($offset = 0; $offset <= 9; $offset++) {
                $urls[] = url('/matches/'.$start->copy()->addDays($offset)->toDateString());
            }

            // Editorial content pages (guides, explainers, trust).
            $urls[] = url('/guides');

            foreach (Config::array('guides') as $page) {
                if (is_array($page) && is_string($page['path'] ?? null)) {
                    $urls[] = url($page['path']);
                }
            }

            $body = '';

            foreach ($urls as $url) {
                $body .= '<url><loc>'.htmlspecialchars($url, ENT_XML1).'</loc></url>';
            }

            return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$body.'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
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
}
