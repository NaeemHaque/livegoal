import { useStorage } from '@vueuse/core';
import { defineStore } from 'pinia';
import { computed } from 'vue';

/**
 * Followed teams and competitions, persisted to localStorage.
 * Each entry is `{ type: 'team' | 'competition', id: string }`.
 */
export const useFavoritesStore = defineStore('favorites', () => {
    const items = useStorage('pp_favorites', []);

    const isFavorite = (type, id) =>
        items.value.some((f) => f.type === type && f.id === String(id));

    const toggle = (type, id) => {
        const key = String(id);

        if (isFavorite(type, key)) {
            items.value = items.value.filter(
                (f) => !(f.type === type && f.id === key),
            );
        } else {
            items.value = [...items.value, { type, id: key }];
        }
    };

    const teamIds = computed(() =>
        items.value.filter((f) => f.type === 'team').map((f) => f.id),
    );
    const competitionIds = computed(() =>
        items.value.filter((f) => f.type === 'competition').map((f) => f.id),
    );

    return { items, isFavorite, toggle, teamIds, competitionIds };
});
