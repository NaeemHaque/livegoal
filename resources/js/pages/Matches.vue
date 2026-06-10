<script setup>
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

import DateNavigator from '@/components/DateNavigator.vue';
import FilterTabs from '@/components/FilterTabs.vue';
import FormationLoader from '@/components/FormationLoader.vue';
import { IcClock, IcLive, IcStar } from '@/components/icons';
import MatchCard from '@/components/MatchCard.vue';
import SectionHead from '@/components/SectionHead.vue';
import EmptyState from '@/components/states/EmptyState.vue';
import ErrorState from '@/components/states/ErrorState.vue';
import { useDayMatches } from '@/composables/useDayMatches';
import { useTimeFormat } from '@/composables/useTimeFormat';
import { useUpcoming } from '@/composables/useUpcoming';
import { today } from '@/lib/dates';
import { useFavoritesStore } from '@/stores/favorites';
import { useMatchesStore } from '@/stores/matches';

const props = defineProps({ date: { type: String, default: '' } });

const router = useRouter();
const favorites = useFavoritesStore();
const matches = useMatchesStore();

const date = ref(props.date || today());
const filter = ref('all');

// Keep the selected day in sync with the URL so dates are shareable and the
// browser back/forward buttons work: route param → date, and date → URL
// (navigating to today drops back to the bare /matches).
watch(
    () => props.date,
    (value) => {
        date.value = value || today();
    },
);
watch(date, (value) => {
    const target = value === today() ? '/matches' : `/matches/${value}`;

    if (router.currentRoute.value.path !== target) {
        router.push(target);
    }
});

const {
    matches: dayMatches,
    loading,
    error,
    reload,
} = useDayMatches(() => date.value);
const all = computed(() => dayMatches.value);

// Next scheduled fixtures across competitions — fills empty days and drives the
// tab counts when the selected day has nothing.
const { data: upcomingData } = useUpcoming();
const time = useTimeFormat();

const LIVE = ['LIVE', 'HT', 'ET', 'PEN'];
const TABS = [
    { id: 'all', label: 'All' },
    { id: 'live', label: 'Live', icon: IcLive },
    { id: 'upcoming', label: 'Upcoming' },
    { id: 'finished', label: 'Finished' },
];

const counts = computed(() => {
    const day = all.value;

    // On an empty day the screen surfaces the cross-day upcoming list, so count
    // that instead — the tabs then reflect what's actually on screen.
    if (day.length === 0 && (upcomingData.value ?? []).length) {
        const total = upcomingData.value.length;

        return { all: total, live: 0, upcoming: total, finished: 0 };
    }

    return {
        all: day.length,
        live: day.filter((m) => LIVE.includes(m.status)).length,
        upcoming: day.filter((m) => m.status === 'SCHEDULED').length,
        finished: day.filter((m) => m.status === 'FT').length,
    };
});

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

// Empty-day upcoming fixtures, grouped by their displayed (local) date.
const upcomingByDate = computed(() => {
    const groups = new Map();

    for (const m of upcomingData.value ?? []) {
        const label = time.date(m.kickoff);

        if (!groups.has(label)) {
            groups.set(label, { label, matches: [] });
        }

        groups.get(label).matches.push(m);
    }

    return [...groups.values()];
});

// Only when the whole day is empty (not merely a filtered subset) — keeps the
// shown list in step with the tab counts.
const showUpcoming = computed(
    () =>
        all.value.length === 0 &&
        (filter.value === 'all' || filter.value === 'upcoming') &&
        upcomingByDate.value.length > 0,
);

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

        <FormationLoader v-if="loading" label="Loading fixtures" />

        <ErrorState v-else-if="error" @retry="reload" />

        <!-- Empty day: surface upcoming fixtures inline (grouped by date). -->
        <template v-else-if="filtered.length === 0 && showUpcoming">
            <div class="pp-section-head" style="margin-bottom: 18px">
                <span class="sh-title"
                    ><IcClock :size="16" /> Upcoming fixtures</span
                >
                <span class="sh-line" />
            </div>
            <div v-for="g in upcomingByDate" :key="g.label" class="pp-section">
                <div class="pp-section-head">
                    <span class="sh-title" style="font-size: 14px">{{
                        g.label
                    }}</span>
                    <span class="sh-count">{{ g.matches.length }}</span>
                    <span class="sh-line" />
                </div>
                <div class="pp-grid cols-2">
                    <MatchCard
                        v-for="m in g.matches"
                        :key="m.id"
                        :match="m"
                        :fav="isFav(m)"
                        @open="open"
                        @fav="toggleFav(m)"
                    />
                </div>
            </div>
        </template>

        <EmptyState
            v-else-if="filtered.length === 0"
            title="No matches on this date"
            text="Nothing scheduled for the selected day and filter."
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
