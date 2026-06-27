<script setup>
import { useDocumentVisibility, useIntervalFn } from '@vueuse/core';
import { computed, ref, watch, watchEffect } from 'vue';
import { useRouter } from 'vue-router';

import Crest from '@/components/Crest.vue';
import {
    IcBall,
    IcCalendar,
    IcChevL,
    IcList,
    IcPin,
    IcTrophy,
    IcWhistle,
} from '@/components/icons';
import InlineLoader from '@/components/InlineLoader.vue';
import LivePulseBadge from '@/components/LivePulseBadge.vue';
import MatchStatus from '@/components/MatchStatus.vue';
import MatchTimeline from '@/components/MatchTimeline.vue';
import ScoreDigit from '@/components/ScoreDigit.vue';
import StandingsTable from '@/components/StandingsTable.vue';
import EmptyState from '@/components/states/EmptyState.vue';
import ErrorState from '@/components/states/ErrorState.vue';
import { useApi } from '@/composables/useApi';
import { useBack } from '@/composables/useBack';
import { useMatch } from '@/composables/useMatch';
import { usePageMeta } from '@/composables/usePageMeta';
import { useTimeFormat } from '@/composables/useTimeFormat';
import { numericId } from '@/lib/slugs';
import { useMatchesStore } from '@/stores/matches';

const props = defineProps({ id: { type: String, required: true } });
const router = useRouter();
const goBack = useBack();
const { time, date, dateTime } = useTimeFormat();

const id = computed(() => numericId(props.id));
const { data: fetched, loading, error, reload } = useMatch(id);
const matchesStore = useMatchesStore();

// ESPN live layer — a real match clock and official event minutes (goals,
// assists, cards, subs) that football-data's free tier lacks. Primary source
// for the live header + timeline; football-data stays the fallback. See the
// `espn-keyless-football-api` note.
const { data: espn, reload: reloadEspn } = useApi(
    () => `/matches/${id.value}/espn`,
);
const espnLive = computed(() => (espn.value?.found ? espn.value : null));

// The site-wide live poll (matches store) is fresher than the cached
// single-match endpoint, which can flap to SCHEDULED/null scores mid-match —
// prefer the live entry's status, minute and score whenever it has this match.
const liveMatch = computed(() => matchesStore.byId(id.value));
const match = computed(() => {
    const base = fetched.value;
    const live = liveMatch.value;

    // Base: football-data + the (fresher) site-wide live poll.
    let m = base ?? live;

    if (base && live) {
        m = {
            ...base,
            status: live.status,
            minute: live.minute ?? base.minute,
            homeScore: live.homeScore ?? base.homeScore,
            awayScore: live.awayScore ?? base.awayScore,
        };
    }

    if (!m) {
        return null;
    }

    // ESPN is authoritative for the live clock/status/score when it resolved
    // this match — football-data values remain the fallback.
    const e = espnLive.value;

    if (e) {
        // Never let a stale/pre ESPN scoreboard revert an already-started match
        // back to SCHEDULED: that flips isLive off and pauses the refresh poll,
        // stranding the page. Forward transitions (LIVE→HT/FT) are still taken.
        const started = ['LIVE', 'HT', 'ET', 'PEN', 'FT'].includes(m.status);
        const status =
            started && e.status === 'SCHEDULED'
                ? m.status
                : (e.status ?? m.status);

        m = {
            ...m,
            status,
            minute: e.minute ?? m.minute,
            displayClock: e.displayClock ?? null,
            homeScore: e.homeScore ?? m.homeScore,
            awayScore: e.awayScore ?? m.awayScore,
        };
    }

    return m;
});

usePageMeta(() => {
    const m = match.value;

    return m?.home?.name && m?.away?.name
        ? `${m.home.name} vs ${m.away.name}`
        : null;
});

const isLive = computed(() =>
    ['LIVE', 'HT', 'ET', 'PEN'].includes(match.value?.status),
);
const isScheduled = computed(() =>
    ['SCHEDULED', 'TIMED', 'POSTPONED'].includes(match.value?.status),
);

// Live matches refresh every 20s while the tab is visible (ScoreDigit flips on change).
const visibility = useDocumentVisibility();
const { pause, resume } = useIntervalFn(
    () => {
        reload();
        reloadEspn();
    },
    20000,
    { immediate: false },
);
watchEffect(() =>
    isLive.value && visibility.value === 'visible' ? resume() : pause(),
);

// Competition standings — only fetched once the match (and its competition) loads.
const compCode = computed(
    () => match.value?.competition?.code || match.value?.competition?.id,
);
const { data: standings, loading: standingsLoading } = useApi(() =>
    compCode.value ? `/competitions/${compCode.value}/standings` : null,
);

const standingGroup = computed(() => {
    const groups = standings.value?.groups ?? [];

    if (!groups.length) {
        return null;
    }

    const ids = [String(match.value?.home?.id), String(match.value?.away?.id)];

    return (
        groups.find((g) =>
            g.rows?.some((r) => ids.includes(String(r.team?.id))),
        ) ?? groups[0]
    );
});

// Timeline events: ESPN's rich events (scorer, assist, cards, subs, official
// minutes) when available, else the poller's self-built goal/HT events.
const timelineEvents = computed(() =>
    espnLive.value?.events?.length
        ? espnLive.value.events
        : (match.value?.events ?? []),
);

const tabs = computed(() => [
    { id: 'summary', label: 'Summary', icon: IcBall },
    ...(standingGroup.value
        ? [{ id: 'standings', label: 'Standings', icon: IcTrophy }]
        : []),
    { id: 'details', label: 'Details', icon: IcList },
]);

// Summary is the default tab once a live or finished match has events; until
// then fall back to standings/details. A manual tab choice is never overridden.
const summaryDefault = computed(
    () =>
        (isLive.value || match.value?.status === 'FT') &&
        timelineEvents.value.length > 0,
);

const tab = ref('details');
const tabTouched = ref(false);

const selectTab = (next) => {
    tab.value = next;
    tabTouched.value = true;
};

watch(
    [summaryDefault, standingGroup],
    () => {
        if (!tabTouched.value) {
            tab.value = summaryDefault.value
                ? 'summary'
                : standingGroup.value
                  ? 'standings'
                  : 'details';
        }
    },
    { immediate: true },
);

const meta = computed(() => {
    const m = match.value;

    if (!m) {
        return [];
    }

    return [
        [
            'stage',
            m.stage && m.stage !== 'REGULAR_SEASON'
                ? m.stage.replaceAll('_', ' ')
                : null,
        ],
        ['group', m.group ? m.group.replace(/^GROUP_/, 'Group ') : null],
        ['kick-off', m.kickoff ? dateTime(m.kickoff) : null],
        ['venue', m.venue],
        ['referee', m.referee],
        ['status', m.status],
    ].filter(([, v]) => v);
});

const openTeam = (teamId) => teamId && router.push(`/team/${teamId}`);
</script>

<template>
    <div class="pp-page pp-rise">
        <button
            class="pp-btn ghost sm"
            type="button"
            style="margin-bottom: 14px"
            @click="goBack"
        >
            <IcChevL :size="15" /> Back
        </button>

        <InlineLoader
            v-if="loading && !match"
            label="Loading match"
            :min-height="240"
        />
        <ErrorState v-else-if="error && !match" @retry="reload" />

        <template v-else-if="match">
            <!-- Score header -->
            <div class="pp-mh">
                <div
                    class="mh-bg"
                    :style="{
                        background: `radial-gradient(120% 120% at 50% -20%, ${match.competition?.color || 'var(--accent)'}33, transparent 60%)`,
                    }"
                />
                <div class="mh-top">
                    <span
                        >{{ match.competition?.name
                        }}<template
                            v-if="
                                match.stage && match.stage !== 'REGULAR_SEASON'
                            "
                        >
                            · {{ match.stage.replaceAll('_', ' ') }}</template
                        ></span
                    >
                    <LivePulseBadge
                        v-if="isLive"
                        :label="match.status === 'HT' ? 'HT' : 'LIVE'"
                    />
                    <MatchStatus v-else :match="match" />
                </div>

                <div class="mh-body">
                    <div
                        class="mh-team"
                        style="cursor: pointer"
                        role="button"
                        tabindex="0"
                        @click="openTeam(match.home?.id)"
                        @keydown.enter="openTeam(match.home?.id)"
                        @keydown.space.prevent="openTeam(match.home?.id)"
                    >
                        <Crest :team="match.home" :size="64" :ring="isLive" />
                        <span class="nm">{{ match.home?.name }}</span>
                    </div>

                    <div class="mh-center">
                        <template v-if="isScheduled">
                            <span
                                class="display tnum"
                                style="font-size: 40px; font-weight: 800"
                                >{{ time(match.kickoff) }}</span
                            >
                            <MatchStatus :match="match" />
                        </template>
                        <template v-else>
                            <span class="mh-score">
                                <ScoreDigit
                                    :value="match.homeScore ?? 0"
                                    :size="60"
                                />
                                <span class="sep">–</span>
                                <ScoreDigit
                                    :value="match.awayScore ?? 0"
                                    :size="60"
                                />
                            </span>
                            <MatchStatus :match="match" />
                        </template>
                    </div>

                    <div
                        class="mh-team"
                        style="cursor: pointer"
                        role="button"
                        tabindex="0"
                        @click="openTeam(match.away?.id)"
                        @keydown.enter="openTeam(match.away?.id)"
                        @keydown.space.prevent="openTeam(match.away?.id)"
                    >
                        <Crest :team="match.away" :size="64" :ring="isLive" />
                        <span class="nm">{{ match.away?.name }}</span>
                    </div>
                </div>

                <div class="mh-meta">
                    <span v-if="match.venue"
                        ><IcPin :size="14" />{{ match.venue }}</span
                    >
                    <span v-if="match.kickoff"
                        ><IcCalendar :size="14" />{{
                            date(match.kickoff)
                        }}</span
                    >
                    <span v-if="match.referee"
                        ><IcWhistle :size="14" />{{ match.referee }}</span
                    >
                </div>
            </div>

            <!-- Tabs -->
            <div class="pp-tabs" style="margin-bottom: 18px">
                <button
                    v-for="t in tabs"
                    :key="t.id"
                    class="tab"
                    :class="{ on: tab === t.id }"
                    type="button"
                    @click="selectTab(t.id)"
                >
                    <component :is="t.icon" :size="15" /> {{ t.label }}
                </button>
            </div>

            <!-- Summary: self-built events timeline -->
            <div v-if="tab === 'summary'" class="pp-panel">
                <h3 class="panel-title">
                    <IcBall :size="16" /> Match events
                    <span v-if="isLive" style="margin-left: auto">
                        <LivePulseBadge
                            small
                            :label="match.status === 'HT' ? 'HT' : 'LIVE'"
                        />
                    </span>
                </h3>
                <MatchTimeline
                    v-if="timelineEvents.length"
                    :match="match"
                    :events="timelineEvents"
                />
                <EmptyState
                    v-else
                    title="No events yet"
                    text="Updates appear live during the match."
                />
            </div>

            <!-- Standings -->
            <div v-else-if="tab === 'standings'" class="pp-panel">
                <h3 class="panel-title">
                    <IcTrophy :size="16" />
                    {{ standingGroup?.label || 'Standings' }}
                </h3>
                <InlineLoader
                    v-if="standingsLoading && !standingGroup"
                    label="Loading table"
                    :min-height="240"
                />
                <StandingsTable
                    v-else-if="standingGroup"
                    :rows="standingGroup.rows"
                    :highlight-id="[match.home?.id, match.away?.id]"
                    @team="openTeam"
                />
                <EmptyState v-else title="No table available" />
            </div>

            <!-- Details -->
            <div v-else class="pp-panel">
                <h3 class="panel-title"><IcList :size="16" /> Match details</h3>
                <dl v-if="meta.length" class="pp-detaillist">
                    <div v-for="[key, value] in meta" :key="key">
                        <dt>{{ key }}</dt>
                        <dd>{{ value }}</dd>
                    </div>
                </dl>
                <EmptyState
                    v-else
                    title="No details available"
                    text="Match information appears closer to kick-off."
                />
            </div>
        </template>

        <EmptyState v-else title="Match not found" />
    </div>
</template>
