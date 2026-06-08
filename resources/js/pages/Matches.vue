<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';

import DateNavigator from '@/components/DateNavigator.vue';
import FilterTabs from '@/components/FilterTabs.vue';
import { IcLive, IcStar } from '@/components/icons';
import MatchCard from '@/components/MatchCard.vue';
import SectionHead from '@/components/SectionHead.vue';
import EmptyState from '@/components/states/EmptyState.vue';
import ErrorState from '@/components/states/ErrorState.vue';
import Skeleton from '@/components/states/Skeleton.vue';
import { useDayMatches } from '@/composables/useDayMatches';
import { today } from '@/lib/dates';
import { useFavoritesStore } from '@/stores/favorites';
import { useMatchesStore } from '@/stores/matches';

const router = useRouter();
const favorites = useFavoritesStore();
const matches = useMatchesStore();

const date = ref(today());
const filter = ref('all');

const {
    matches: dayMatches,
    loading,
    error,
    reload,
} = useDayMatches(() => date.value);
const all = computed(() => dayMatches.value);

const LIVE = ['LIVE', 'HT', 'ET', 'PEN'];
const TABS = [
    { id: 'all', label: 'All' },
    { id: 'live', label: 'Live', icon: IcLive },
    { id: 'upcoming', label: 'Upcoming' },
    { id: 'finished', label: 'Finished' },
];

const counts = computed(() => ({
    all: all.value.length,
    live: all.value.filter((m) => LIVE.includes(m.status)).length,
    upcoming: all.value.filter((m) => m.status === 'SCHEDULED').length,
    finished: all.value.filter((m) => m.status === 'FT').length,
}));

const filtered = computed(() =>
    all.value.filter((m) => {
        if (filter.value === 'live') {
            return LIVE.includes(m.status);
        }

        if (filter.value === 'upcoming') {
            return m.status === 'SCHEDULED';
        }

        if (filter.value === 'finished') {
            return m.status === 'FT';
        }

        return true;
    }),
);

const isFav = (m) => favorites.isMatchFavorite(m);
const favMatches = computed(() => filtered.value.filter(isFav));
const restGroups = computed(() => {
    const groups = new Map();

    for (const m of filtered.value.filter((m) => !isFav(m))) {
        const key = m.competition?.id ?? '?';

        if (!groups.has(key)) {
            groups.set(key, { competition: m.competition, matches: [] });
        }

        groups.get(key).matches.push(m);
    }

    return [...groups.values()];
});

const open = (m) => router.push(`/match/${m.id}`);
const toggleFav = (m) => favorites.toggleMatchFavorite(m);
</script>

<template>
    <div class="pp-page pp-rise">
        <div class="pp-pagehead">
            <div>
                <h1>Matches</h1>
                <div class="ph-sub">Browse fixtures &amp; results by date</div>
            </div>
        </div>

        <div
            style="
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                flex-wrap: wrap;
                margin-bottom: 20px;
            "
        >
            <FilterTabs v-model="filter" :tabs="TABS" :counts="counts" />
            <DateNavigator v-model="date" :live-count="counts.live" />
        </div>

        <div v-if="loading" class="pp-grid cols-2">
            <Skeleton v-for="i in 6" :key="i" :h="76" :r="14" />
        </div>

        <ErrorState v-else-if="error" @retry="reload" />

        <EmptyState
            v-else-if="filtered.length === 0"
            title="Nothing here"
            text="No matches for this date and filter."
        />

        <template v-else>
            <div v-if="favMatches.length" class="pp-section">
                <div class="pp-section-head">
                    <span class="sh-title"
                        ><IcStar :size="16" style="color: var(--draw)" />
                        Following</span
                    >
                    <span class="sh-line" />
                </div>
                <div class="pp-grid cols-2">
                    <MatchCard
                        v-for="m in favMatches"
                        :key="m.id"
                        :match="m"
                        fav
                        :scored="matches.justScoredId === String(m.id)"
                        @open="open"
                        @fav="toggleFav(m)"
                    />
                </div>
            </div>

            <div
                v-for="group in restGroups"
                :key="group.competition?.id"
                class="pp-section"
            >
                <SectionHead
                    :competition="group.competition"
                    :count="group.matches.length"
                />
                <div class="pp-grid cols-2">
                    <MatchCard
                        v-for="m in group.matches"
                        :key="m.id"
                        :match="m"
                        :fav="isFav(m)"
                        :scored="matches.justScoredId === String(m.id)"
                        @open="open"
                        @fav="toggleFav(m)"
                    />
                </div>
            </div>
        </template>
    </div>
</template>
