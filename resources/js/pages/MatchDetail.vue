<script setup>
import { useDocumentVisibility, useIntervalFn } from '@vueuse/core';
import { computed, ref, watch, watchEffect } from 'vue';
import { useRouter } from 'vue-router';

import Crest from '@/components/Crest.vue';
import {
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

const props = defineProps({ id: { type: String, required: true } });
const router = useRouter();
const goBack = useBack();
const { time, date, dateTime } = useTimeFormat();

const id = computed(() => numericId(props.id));
const { data: match, loading, error, reload } = useMatch(id);

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
const { pause, resume } = useIntervalFn(reload, 20000, { immediate: false });
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

const tabs = computed(() => [
    ...(standingGroup.value
        ? [{ id: 'standings', label: 'Standings', icon: IcTrophy }]
        : []),
    { id: 'details', label: 'Details', icon: IcList },
]);

const tab = ref('details');
watch(standingGroup, (g) => (tab.value = g ? 'standings' : 'details'), {
    immediate: true,
});

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
        ['group', m.group],
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
        <ErrorState v-else-if="error" @retry="reload" />

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
                    <LivePulseBadge v-if="isLive" />
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
                            <MatchStatus v-if="!isLive" :match="match" />
                            <span
                                v-else
                                class="mono"
                                style="
                                    color: var(--text-muted);
                                    font-size: 13px;
                                "
                            >
                                {{
                                    match.minute != null
                                        ? `${match.minute}'`
                                        : 'Live'
                                }}
                            </span>
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
                    @click="tab = t.id"
                >
                    <component :is="t.icon" :size="15" /> {{ t.label }}
                </button>
            </div>

            <!-- Standings -->
            <div v-if="tab === 'standings'" class="pp-panel">
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
