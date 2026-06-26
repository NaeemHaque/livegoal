/**
 * Fire a GA4 event through the global `gtag`, which is only present when
 * GOOGLE_ANALYTICS_ID is configured (see resources/views/partials/seo-head.blade.php).
 * A no-op otherwise, so it's safe to call anywhere — local/dev with no GA stays
 * silent and there's nothing to guard at each call site.
 *
 * @param {string} name GA4 event name (e.g. 'search', 'favorite').
 * @param {Record<string, unknown>} [params] Event parameters.
 */
export function track(name, params = {}) {
    if (typeof window !== 'undefined' && typeof window.gtag === 'function') {
        window.gtag('event', name, params);
    }
}
