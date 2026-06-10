<?php

namespace App\Http\Controllers;

use App\Seo\SeoMeta;
use App\Seo\SeoMetaResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/**
 * Serves the Vue SPA shell (resources/views/app.blade.php) for every UI route,
 * injecting a per-URL SeoMeta so crawlers and social/AI bots receive real
 * title/description/canonical/Open Graph/JSON-LD instead of a blank shell. The
 * browser still boots the same SPA from the `id="app"` mount point.
 */
class SeoShellController extends Controller
{
    public function __construct(private readonly SeoMetaResolver $seo) {}

    public function home(): View
    {
        return $this->shell($this->seo->home());
    }

    public function matches(): View
    {
        return $this->shell($this->seo->matches());
    }

    public function competitions(): View
    {
        return $this->shell($this->seo->competitions());
    }

    public function scorers(): View
    {
        return $this->shell($this->seo->scorers());
    }

    public function match(string $id): View
    {
        return $this->shell($this->seo->match($id), $this->seo->matchBody($id));
    }

    public function competition(string $id): View
    {
        return $this->shell($this->seo->competition($id), $this->seo->competitionBody($id));
    }

    public function team(string $id): View
    {
        return $this->shell($this->seo->team($id), $this->seo->teamBody($id));
    }

    public function player(string $id): View
    {
        return $this->shell($this->seo->player($id), $this->seo->playerBody($id));
    }

    public function favorites(): View
    {
        return $this->shell($this->seo->utility('favorites'));
    }

    public function search(): View
    {
        return $this->shell($this->seo->utility('search'));
    }

    public function settings(): View
    {
        return $this->shell($this->seo->utility('settings'));
    }

    public function notFound(): Response
    {
        return response()->view('app', ['seo' => $this->seo->notFound()], 404);
    }

    /**
     * @param  array{view: string, data: array<string, mixed>}|null  $prerender
     */
    private function shell(SeoMeta $seo, ?array $prerender = null): View
    {
        return view('app', ['seo' => $seo, 'prerender' => $prerender]);
    }
}
