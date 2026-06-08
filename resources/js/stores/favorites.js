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

    /** A match counts as followed when either side is a followed team. */
    const isMatchFavorite = (match) =>
        isFavorite('team', match?.home?.id) ||
        isFavorite('team', match?.away?.id);

    /**
     * Toggle a match card's star: unfollow whichever side(s) made it followed,
     * otherwise follow the home team. Keeps the star a clean on/off toggle
     * instead of silently accruing extra favorites.
     */
    const toggleMatchFavorite = (match) => {
        if (isMatchFavorite(match)) {
            if (isFavorite('team', match?.home?.id)) {
                toggle('team', match.home.id);
            }

            if (isFavorite('team', match?.away?.id)) {
                toggle('team', match.away.id);
            }
        } else if (match?.home?.id) {
            toggle('team', match.home.id);
        }
    };

    const teamIds = computed(() =>
        items.value.filter((f) => f.type === 'team').map((f) => f.id),
    );
    const competitionIds = computed(() =>
        items.value.filter((f) => f.type === 'competition').map((f) => f.id),
    );

    return {
        items,
        isFavorite,
        toggle,
        isMatchFavorite,
        toggleMatchFavorite,
        teamIds,
        competitionIds,
    };
});
