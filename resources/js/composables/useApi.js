import { ref, shallowRef, toValue, watch } from 'vue';

import api from '@/services/api';

/**
 * Fetch a `{ data, meta }` envelope from the API into reactive state. `path` may
 * be a string, ref, or getter — when it's reactive the data reloads on change.
 *
 * @param {string|(() => string)} path
 * @param {{ immediate?: boolean }} [options]
 */
export function useApi(path, { immediate = true } = {}) {
    const data = shallowRef(null);
    const meta = shallowRef(null);
    const loading = ref(false);
    const error = ref(null);

    // Monotonic token so a slow earlier request can't overwrite a newer one
    // (e.g. fast navigation between detail pages).
    let activeRequest = 0;

    async function load() {
        const requestId = ++activeRequest;
        loading.value = true;
        error.value = null;

        try {
            const res = await api.get(toValue(path));

            if (requestId !== activeRequest) {
                return;
            }

            data.value = res.data?.data ?? null;
            meta.value = res.data?.meta ?? null;
        } catch (e) {
            if (requestId !== activeRequest) {
                return;
            }

            error.value = e;
            data.value = null;
            meta.value = null;
        } finally {
            if (requestId === activeRequest) {
                loading.value = false;
            }
        }
    }

    if (immediate) {
        load();
    }

    // Reload when a reactive path changes (e.g. a route param).
    watch(
        () => toValue(path),
        () => load(),
    );

    return { data, meta, loading, error, reload: load };
}
