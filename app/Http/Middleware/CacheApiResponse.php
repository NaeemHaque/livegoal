<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds browser/CDN caching to successful GET responses on the cached JSON API.
 *
 * Sets a public Cache-Control with a per-endpoint max-age (the route passes the
 * freshness window in seconds, matched to the data's volatility) plus an ETag,
 * and answers conditional requests (If-None-Match) with 304 — so repeat polls
 * and a CDN edge cost almost nothing. Error responses are never cached.
 */
class CacheApiResponse
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $maxAge = '60'): Response
    {
        $response = $next($request);

        if ($request->getMethod() !== 'GET' || $response->getStatusCode() !== 200) {
            return $response;
        }

        $seconds = (int) $maxAge;
        $staleWhileRevalidate = $seconds * 4;

        $response->headers->set(
            'Cache-Control',
            "public, max-age={$seconds}, s-maxage={$seconds}, stale-while-revalidate={$staleWhileRevalidate}",
        );

        $content = $response->getContent();

        if (is_string($content) && $content !== '') {
            $etag = '"'.md5($content).'"';
            $response->headers->set('ETag', $etag);

            $ifNoneMatch = $request->headers->get('If-None-Match');

            if ($ifNoneMatch !== null && trim($ifNoneMatch) === $etag) {
                $response->setNotModified();
            }
        }

        return $response;
    }
}
