import { toValue, watch } from 'vue';

const SITE = 'LiveGoal';

/**
 * Keep the browser tab title in sync with a page's primary entity as it loads.
 *
 * The server renders the correct <title> on first paint; on client-side SPA
 * navigation the router sets a generic per-route title, and detail pages call
 * this with a ref/computed/getter resolving to the real entity name. The site
 * name is appended. Falsy values are ignored, so a generic title stays until
 * the real data arrives.
 */
export function usePageMeta(title) {
    watch(
        () => toValue(title),
        (value) => {
            if (value) {
                document.title = `${value} | ${SITE}`;
            }
        },
        { immediate: true },
    );
}
