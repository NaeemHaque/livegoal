import { toValue } from 'vue';

import { useApi } from '@/composables/useApi';

export function useTeam(id) {
    return useApi(() => `/teams/${toValue(id)}`);
}
