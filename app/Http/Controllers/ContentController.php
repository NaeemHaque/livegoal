<?php

namespace App\Http\Controllers;

use App\Seo\SeoMeta;
use App\Seo\SeoMetaResolver;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Config;

/**
 * Serves the server-rendered editorial content pages (guides, explainers, trust)
 * defined in config/guides.php. These are evergreen, crawlable HTML — the AEO/GEO
 * content layer — rendered through the shared layouts.content layout.
 */
class ContentController extends Controller
{
    public function __construct(
        private readonly SeoMetaResolver $seo,
        private readonly ViewFactory $views,
    ) {}

    public function index(): View
    {
        /** @var array<string, array<string, mixed>> $pages */
        $pages = Config::array('guides');

        $listed = array_filter($pages, fn (array $page): bool => ($page['listed'] ?? false) === true);

        $grouped = [];

        foreach ($listed as $slug => $page) {
            $grouped[$this->str($page['group'] ?? 'Guides')][$slug] = $page;
        }

        return view('content.index', [
            'seo' => new SeoMeta(
                title: 'Football Guides & World Cup 2026 Explainers | '.Config::string('seo.site_name'),
                description: 'Plain-English guides to the 2026 World Cup format, groups, knockout bracket, how to watch free, and a football glossary.',
                canonical: url('/guides'),
            ),
            'grouped' => $grouped,
        ]);
    }

    public function show(string $slug): View
    {
        $guide = Config::get("guides.{$slug}");

        if (is_array($guide)) {
            $page = [
                'path' => $this->str($guide['path'] ?? null),
                'title' => $this->str($guide['title'] ?? null),
                'description' => $this->str($guide['description'] ?? null),
                'group' => $this->str($guide['group'] ?? null),
            ];

            $data = [
                'seo' => $this->seo->content($page['path'], $page['title'], $page['description']),
                'page' => $page,
            ];

            // WC explainers embed the live group tables + fixtures — the unique angle.
            if (($guide['live'] ?? null) === 'WC') {
                $data['live'] = $this->seo->worldCupSnapshot();
            }

            return $this->views->make($this->str($guide['view'] ?? null), $data);
        }

        $term = Config::get("glossary.{$slug}");

        if (is_array($term)) {
            $page = [
                'path' => '/guides/'.$slug,
                'title' => $this->str($term['title'] ?? null),
                'description' => $this->str($term['description'] ?? null),
                'group' => 'Football glossary',
            ];

            return $this->views->make('content.guides.glossary-term', [
                'seo' => $this->seo->content($page['path'], $page['title'], $page['description']),
                'page' => $page,
                'term' => $term,
            ]);
        }

        abort(404);
    }

    /**
     * A per-country "how to watch free" page (the winnable long-tail).
     */
    public function watch(string $country): View
    {
        $data = Config::get("watch.{$country}");

        abort_if(! is_array($data), 404);

        $name = $this->str($data['country'] ?? $country);

        $page = [
            'path' => '/guides/how-to-watch-world-cup-2026-free/'.$country,
            'title' => 'How to Watch the World Cup 2026 Free in '.$name,
            'description' => 'Where to watch the 2026 World Cup free in '.$name.': the free-to-air channels, free streams, and how many of the 104 matches are free.',
            'group' => 'World Cup 2026',
        ];

        return $this->views->make('content.guides.watch-country', [
            'seo' => $this->seo->content($page['path'], $page['title'], $page['description']),
            'page' => $page,
            'watch' => $data,
        ]);
    }

    private function str(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
