import { useRouter } from 'vue-router';

/**
 * Returns a `goBack` handler that navigates to the previous in-app page, or to a
 * fallback route when there's no app history (e.g. the page was opened directly
 * by URL, where `router.back()` would otherwise do nothing or leave the site).
 *
 * @param {string} [fallback]
 */
export function useBack(fallback = '/') {
    const router = useRouter();

    return () => {
        if (window.history.state?.back != null) {
            router.back();
        } else {
            router.push(fallback);
        }
    };
}
