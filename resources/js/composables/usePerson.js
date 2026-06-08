import { toValue } from 'vue';

import { useApi } from '@/composables/useApi';

export function usePerson(id) {
    return useApi(() => `/persons/${toValue(id)}`);
}
