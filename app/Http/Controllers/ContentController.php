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
        /** @var array<string, mixed>|null $page */
        $page = Config::get("guides.{$slug}");

        abort_if(! is_array($page), 404);

        /** @var array{path: string, title: string, description: string, group: string, view: string} $page */
        return $this->views->make($this->str($page['view']), [
            'seo' => $this->seo->content($page),
            'page' => $page,
        ]);
    }

    private function str(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
