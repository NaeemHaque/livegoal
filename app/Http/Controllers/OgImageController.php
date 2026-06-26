<?php

namespace App\Http\Controllers;

use App\Seo\OgImage;
use App\Seo\Slug;
use App\Services\Football\FeaturedMatches;
use App\Services\Football\FootballData;
use App\Services\Football\Normalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Renders dynamic Open Graph share images so a shared match link shows the teams
 * and score, not the generic site card — a real CTR/shares lever during a
 * tournament. Reads are cache-only (crawler/scraper-safe); a missing entity or
 * an unavailable GD/font falls back to the static og-image.png.
 */
class OgImageController extends Controller
{
    public function __construct(
        private readonly FootballData $football,
        private readonly Normalizer $normalizer,
        private readonly FeaturedMatches $featured,
        private readonly OgImage $og,
    ) {}

    public function match(string $id): Response|RedirectResponse
    {
        $numericId = Slug::id($id);
        $raw = $this->football->peek("match:{$numericId}");

        // Prefer the per-match cache (fuller), else resolve from the warmed
        // competition feeds so a card renders even before anyone opens the match.
        $m = $raw !== null ? $this->normalizer->match($raw) : $this->featured->findById($numericId);

        if ($m === null) {
            return $this->fallback();
        }

        $home = $this->str(data_get($m, 'home.name'));
        $away = $this->str(data_get($m, 'away.name'));

        if ($home === '' || $away === '') {
            return $this->fallback();
        }

        $status = $this->str(data_get($m, 'status'));
        $eyebrow = $this->str(data_get($m, 'competition.name'));
        $subtitle = $this->subtitle($m, $status);

        // Regenerate when the score/status changes (signature in the cache key).
        $signature = md5($status.'|'.$subtitle);

        $png = Cache::remember(
            "og:match:{$numericId}:{$signature}",
            3600,
            fn (): ?string => $this->og->card($eyebrow, "{$home} vs {$away}", $subtitle),
        );

        if (! is_string($png)) {
            // The match resolved but rendering failed — GD or a TTF font is
            // missing on the host. Surface it (throttled) so it's not silent.
            if (Cache::add('og:render-failed-logged', true, 3600)) {
                Log::warning('OG image render failed — check that ext-gd and a TTF font (e.g. fonts-dejavu-core) are installed.');
            }

            return $this->fallback();
        }

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=600',
        ]);
    }

    /**
     * @param  array<string, mixed>  $m
     */
    private function subtitle(array $m, string $status): string
    {
        $score = $this->int(data_get($m, 'homeScore')).'–'.$this->int(data_get($m, 'awayScore'));

        if (in_array($status, ['FT', 'AET', 'PEN'], true)) {
            return 'Full time · '.$score;
        }

        if (in_array($status, ['LIVE', 'HT', 'ET'], true)) {
            return 'Live · '.$score;
        }

        $kickoff = $this->str(data_get($m, 'kickoff'));

        if ($kickoff !== '') {
            try {
                return Carbon::parse($kickoff)->format('D j M Y, H:i').' UTC';
            } catch (\Throwable) {
                // Fall through to the generic subtitle.
            }
        }

        return 'Live score & result';
    }

    private function fallback(): RedirectResponse
    {
        return redirect(url(Config::string('seo.og_image')));
    }

    private function str(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
