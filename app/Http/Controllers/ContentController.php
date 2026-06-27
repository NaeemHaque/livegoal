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
                'seo' => $this->seo->content($page['path'], $page['title'], $page['description'], $this->faqList($guide['faq'] ?? null)),
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
            'seo' => $this->seo->content($page['path'], $page['title'], $page['description'], $this->watchFaq($name, $data)),
            'page' => $page,
            'watch' => $data,
        ]);
    }

    /**
     * Coerce a config FAQ block to a clean question/answer list.
     *
     * @return list<array{q: string, a: string}>
     */
    private function faqList(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];

        foreach ($raw as $item) {
            if (is_array($item) && is_string($item['q'] ?? null) && is_string($item['a'] ?? null)) {
                $out[] = ['q' => $item['q'], 'a' => $item['a']];
            }
        }

        return $out;
    }

    /**
     * The FAQ for a "how to watch free" country page, mirroring the visible copy.
     *
     * @param  array<array-key, mixed>  $data
     * @return list<array{q: string, a: string}>
     */
    private function watchFaq(string $name, array $data): array
    {
        $scope = $this->str($data['scope'] ?? null);
        $free = ($data['free'] ?? false) === true;

        $faq = [
            [
                'q' => 'Can I watch the World Cup 2026 free in '.$name.'?',
                'a' => ($free
                    ? 'Yes — you can watch the 2026 World Cup free in '.$name.'. '
                    : 'Some matches are free in '.$name.', but not all. ').$scope,
            ],
            [
                'q' => 'Which channels show the World Cup 2026 free in '.$name.'?',
                'a' => 'In '.$name.', the 2026 World Cup is shown free-to-air on '.$this->str($data['fta'] ?? null).'. '.$scope,
            ],
        ];

        if ($this->str($data['streaming'] ?? null) !== '') {
            $faq[] = [
                'q' => 'Can I stream the World Cup 2026 free in '.$name.'?',
                'a' => $this->str($data['streaming']),
            ];
        }

        if ($this->str($data['paid'] ?? null) !== '') {
            $faq[] = [
                'q' => 'How can I watch every World Cup 2026 match in '.$name.'?',
                'a' => $this->str($data['paid']),
            ];
        }

        return $faq;
    }

    private function str(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
