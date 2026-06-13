import { useStorage } from '@vueuse/core';
import { computed, reactive } from 'vue';

/**
 * Followed teams and competitions, persisted to localStorage.
 * Each entry is `{ type: 'team' | 'competition', id: string }`.
 *
 * A native reactive singleton — no Pinia. One shared instance per app, created
 * when this module is first imported.
 */
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
    isFavorite('team', match?.home?.id) || isFavorite('team', match?.away?.id);

/**
 * Toggle a match card's star: unfollow whichever side(s) made it followed,
 * otherwise follow the home team. Keeps the star a clean on/off toggle instead
 * of silently accruing extra favorites.
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

const store = reactive({
    items,
    isFavorite,
    toggle,
    isMatchFavorite,
    toggleMatchFavorite,
    teamIds,
    competitionIds,
});

export function useFavoritesStore() {
    return store;
}
