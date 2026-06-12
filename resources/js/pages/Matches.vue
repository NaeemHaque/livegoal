<script setup>
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

import CountryFilter from '@/components/CountryFilter.vue';
import DateNavigator from '@/components/DateNavigator.vue';
import FilterTabs from '@/components/FilterTabs.vue';
import FormationLoader from '@/components/FormationLoader.vue';
import { IcClock, IcLive, IcStar } from '@/components/icons';
import MatchCard from '@/components/MatchCard.vue';
import SectionHead from '@/components/SectionHead.vue';
import EmptyState from '@/components/states/EmptyState.vue';
import ErrorState from '@/components/states/ErrorState.vue';
import { useDayMatches } from '@/composables/useDayMatches';
import { useResults } from '@/composables/useResults';
import { useTimeFormat } from '@/composables/useTimeFormat';
import { useUpcoming } from '@/composables/useUpcoming';
import { useFavoritesStore } from '@/stores/favorites';
import { useMatchesStore } from '@/stores/matches';

const props = defineProps({ date: { type: String, default: '' } });

const router = useRouter();
const favorites = useFavoritesStore();
const matches = useMatchesStore();

// Empty = no date picked → the default view shows the full upcoming list.
// Picking a date (incl. today) switches to that single day's fixtures.
const date = ref(props.date);
const filter = ref('all');

// Keep the selected day in sync with the URL so dates are shareable and the
// browser back/forward buttons work: route param → date, and date → URL.
// The bare /matches is the no-date upcoming view.
watch(
    () => props.date,
    (value) => {
        date.value = value;
    },
);
watch(date, (value) => {
    const target = value ? `/matches/${value}` : '/matches';

    if (router.currentRoute.value.path !== target) {
        router.push(target);
    }
});

const dateSelected = computed(() => date.value !== '');

// Multi-select country/team filter — names of selected sides.
const countries = ref([]);

const {
    matches: dayMatches,
    loading,
    error,
    reload,
} = useDayMatches(() => date.value);
// Overlay the live feed so mid-match status/score lag in the upstream list
// endpoints never shows a live match as scheduled here.
const all = computed(() => matches.overlay(dayMatches.value));

// Next scheduled fixtures across competitions — fills empty days and drives the
// tab counts when the selected day has nothing.
const { data: upcomingData } = useUpcoming();
const upcomingAll = computed(() => matches.overlay(upcomingData.value));

// Latest results (newest first) — the Finished view when no date is picked.
const { data: resultsData } = useResults();
const resultsAll = computed(() => matches.overlay(resultsData.value));
const time = useTimeFormat();

// Every side appearing in the visible lists; at the World Cup these are
// countries. Drives the searchable multi-select beside the date control.
const countryPool = computed(() => {
    const seen = new Map();

    for (const m of [...all.value, ...upcomingAll.value]) {
        for (const side of [m.home, m.away]) {
            if (side?.name && !seen.has(side.name)) {
                seen.set(side.name, {
                    name: side.name,
                    crest: side.crest ?? null,
                    tla: side.tla ?? side.short ?? null,
                });
            }
        }
    }

    return [...seen.values()].sort((a, b) => a.name.localeCompare(b.name));
});

const byCountry = (m) =>
    countries.value.length === 0 ||
    countries.value.includes(m.home?.name) ||
    countries.value.includes(m.away?.name);

const LIVE = ['LIVE', 'HT', 'ET', 'PEN'];
const TABS = [
    { id: 'all', label: 'All' },
    { id: 'live', label: 'Live', icon: IcLive },
    { id: 'upcoming', label: 'Upcoming' },
    { id: 'finished', label: 'Finished' },
];

const statusCounts = (list) => ({
    all: list.length,
    live: list.filter((m) => LIVE.includes(m.status)).length,
    upcoming: list.filter((m) => m.status === 'SCHEDULED').length,
    finished: list.filter((m) => m.status === 'FT').length,
});

const counts = computed(() => {
    // On an empty day the screen surfaces the cross-day upcoming list, so count
    // that instead — the tabs then reflect what's actually on screen.
    const noDay = all.value.length === 0;
    const base = noDay
        ? statusCounts(upcomingAll.value.filter(byCountry))
        : statusCounts(all.value.filter(byCountry));

    // With no day selected, Finished counts the recent-results feed, and
    // "Live" always reflects the site-wide live feed (date-independent).
    return {
        ...base,
        finished: noDay
            ? resultsAll.value.filter(byCountry).length
            : base.finished,
        live: matches.live.filter(byCountry).length,
    };
});

const byTab = (m) => {
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
};

const filtered = computed(() => {
    // The Live tab is date-independent: always the current live feed, even
    // when browsing another day (matches are grouped by UTC date upstream).
    if (filter.value === 'live') {
        return matches.live.filter(byCountry);
    }

    return all.value.filter((m) => byTab(m) && byCountry(m));
});

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

// No-day fallback list: recent results on the Finished tab (newest first),
// upcoming fixtures elsewhere (filtered by the active tab, so a live match
// shows under Live — not Upcoming). Grouped by their displayed (local) date.
const upcomingByDate = computed(() => {
    const source =
        filter.value === 'finished'
            ? resultsAll.value.filter(byCountry)
            : upcomingAll.value.filter(
                  (match) => byTab(match) && byCountry(match),
              );

    const groups = new Map();

    for (const m of source) {
        const label = time.date(m.kickoff);

        if (!groups.has(label)) {
            groups.set(label, { label, matches: [] });
        }

        groups.get(label).matches.push(m);
    }

    return [...groups.values()];
});

// Default (no date picked) always shows the full upcoming list; a picked date
// that turns out empty still falls back to it.
const showUpcoming = computed(
    () =>
        upcomingByDate.value.length > 0 &&
        (!dateSelected.value || all.value.length === 0),
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
            <div
                style="
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    flex-wrap: wrap;
                "
            >
                <CountryFilter v-model="countries" :options="countryPool" />
                <DateNavigator
                    :model-value="date"
                    :live-count="counts.live"
                    @update:model-value="(value) => (date = value)"
                />
            </div>
        </div>

        <FormationLoader v-if="loading" label="Loading fixtures" />

        <ErrorState v-else-if="error" @retry="reload" />

        <!-- Empty day: surface upcoming fixtures inline (grouped by date). -->
        <template v-else-if="filtered.length === 0 && showUpcoming">
            <div class="pp-section-head" style="margin-bottom: 18px">
                <span class="sh-title"
                    ><IcClock :size="16" />
                    {{
                        filter === 'live'
                            ? 'Live now'
                            : filter === 'finished'
                              ? 'Recent results'
                              : 'Upcoming fixtures'
                    }}</span
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
