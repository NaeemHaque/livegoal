<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';

import Crest from '@/components/Crest.vue';
import { IcArrowR, IcCalendar, IcClock, IcTrophy } from '@/components/icons';
import InlineLoader from '@/components/InlineLoader.vue';
import LivePulseBadge from '@/components/LivePulseBadge.vue';
import MatchCard from '@/components/MatchCard.vue';
import NextKickoff from '@/components/NextKickoff.vue';
import SectionHead from '@/components/SectionHead.vue';
import StandingsTable from '@/components/StandingsTable.vue';
import EmptyState from '@/components/states/EmptyState.vue';
import { useScorers } from '@/composables/useScorers';
import { useStandings } from '@/composables/useStandings';
import { useTimeFormat } from '@/composables/useTimeFormat';
import { useUpcoming } from '@/composables/useUpcoming';
import { useFavoritesStore } from '@/stores/favorites';
import { useMatchesStore } from '@/stores/matches';

const router = useRouter();
const matches = useMatchesStore();
const favorites = useFavoritesStore();

const { data: upcomingData, loading } = useUpcoming();
const { data: standings } = useStandings('PL');
const { data: wcScorers } = useScorers('WC');
const { data: leagueScorers } = useScorers('PL');
const time = useTimeFormat();

// The World Cup 2026 window for the spotlight. Formatted from fixed dates so it
// paints with the rest of the banner — no async fetch, no flash, no layout shift.
const wcWindow = time.dateRange('2026-06-11', '2026-07-19');

const live = computed(() => matches.live);
const liveWc = computed(
    () => live.value.filter((m) => m.competition?.code === 'WC').length,
);
// The next handful of scheduled fixtures (server already filtered + sorted).
const upcoming = computed(() => (upcomingData.value ?? []).slice(0, 6));
const nextMatch = computed(() => (upcomingData.value ?? [])[0] ?? null);

// Top of the table — a league standings snapshot.
const tableRows = computed(() =>
    (standings.value?.groups?.[0]?.rows ?? []).slice(0, 6),
);

// Feature the World Cup scorers while it's upcoming or under way; once it's no
// longer current (no fixtures, no scorers) fall back to a league's golden boot.
const wcCurrent = computed(
    () =>
        (wcScorers.value?.length ?? 0) > 0 ||
        (upcomingData.value ?? []).some((m) => m.competition?.code === 'WC'),
);
const scorerTitle = computed(() =>
    wcCurrent.value ? 'World Cup top scorers' : 'Premier League top scorers',
);
const topScorers = computed(() =>
    ((wcCurrent.value ? wcScorers.value : leagueScorers.value) ?? []).slice(
        0,
        5,
    ),
);
const wcStartLabel = computed(() =>
    nextMatch.value ? time.date(nextMatch.value.kickoff) : null,
);

const upcomingGroups = computed(() => {
    const groups = new Map();

    for (const m of upcoming.value) {
        const key = m.competition?.id ?? '?';

        if (!groups.has(key)) {
            groups.set(key, { competition: m.competition, matches: [] });
        }

        groups.get(key).matches.push(m);
    }

    return [...groups.values()];
});

const openMatch = (m) => router.push(`/match/${m.id}`);
const isFav = (m) => favorites.isMatchFavorite(m);
const toggleFav = (m) => favorites.toggleMatchFavorite(m);
</script>

<template>
    <div class="pp-page pp-rise">
        <div class="pp-pagehead">
            <div>
                <h1>Live Hub</h1>
                <div class="ph-sub">{{ live.length }} live now</div>
            </div>
        </div>

        <!-- World Cup spotlight -->
        <div class="pp-spotlight">
            <div class="sp-inner">
                <div>
                    <div class="sp-tag">
                        <IcTrophy :size="14" /> Featured Competition
                    </div>
                    <h2>FIFA World Cup 2026</h2>
                    <div class="sp-meta">USA · Canada · Mexico</div>
                    <div class="sp-dates">
                        <IcCalendar :size="14" /> {{ wcWindow }}
                    </div>
                </div>
                <div class="sp-stats">
                    <div class="sp-stat">
                        <b class="display tnum">{{ liveWc }}</b
                        ><small>Live now</small>
                    </div>
                    <div class="sp-stat">
                        <b class="display tnum">48</b><small>Teams</small>
                    </div>
                    <div class="sp-stat">
                        <b class="display tnum">12</b><small>Groups</small>
                    </div>
                    <div class="sp-stat" style="justify-content: flex-end">
                        <button
                            class="pp-btn primary"
                            type="button"
                            @click="router.push('/competition/WC')"
                        >
                            Explore <IcArrowR :size="16" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="pp-hub">
            <div>
                <!-- Live now -->
                <div class="pp-section">
                    <div class="pp-section-head">
                        <span class="sh-title"
                            ><LivePulseBadge /> Live now</span
                        >
                        <span class="sh-count">{{ live.length }} matches</span>
                        <span class="sh-line" />
                    </div>
                    <div
                        v-if="live.length"
                        class="pp-grid"
                        style="
                            grid-template-columns: repeat(
                                auto-fit,
                                minmax(420px, 1fr)
                            );
                        "
                    >
                        <MatchCard
                            v-for="m in live"
                            :key="m.id"
                            :match="m"
                            expanded
                            :fav="isFav(m)"
                            :scored="matches.justScoredId === String(m.id)"
                            @open="openMatch"
                            @fav="toggleFav(m)"
                        />
                    </div>
                    <NextKickoff v-else-if="nextMatch" :match="nextMatch" />
                    <EmptyState
                        v-else
                        title="No live matches"
                        text="Check back at kickoff — live scores appear here."
                    />
                </div>

                <!-- Upcoming today -->
                <div class="pp-section">
                    <div class="pp-section-head">
                        <span class="sh-title"
                            ><IcClock :size="17" /> Upcoming</span
                        >
                        <span class="sh-line" />
                        <button
                            class="pp-btn ghost sm"
                            type="button"
                            @click="router.push('/matches')"
                        >
                            All fixtures <IcArrowR :size="14" />
                        </button>
                    </div>

                    <InlineLoader
                        v-if="loading"
                        label="Loading fixtures"
                        :min-height="180"
                    />
                    <template v-else-if="upcomingGroups.length">
                        <div
                            v-for="group in upcomingGroups"
                            :key="group.competition?.id"
                            style="margin-bottom: 18px"
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
                                    show-date
                                    @open="openMatch"
                                    @fav="toggleFav(m)"
                                />
                            </div>
                        </div>
                    </template>
                    <EmptyState
                        v-else
                        title="No upcoming fixtures"
                        text="Scheduled matches will appear here as soon as they're announced."
                    />
                </div>
            </div>

            <!-- Right rail -->
            <aside class="pp-rail">
                <!-- Top scorers — World Cup while it's current, else a league -->
                <div class="pp-rail-card">
                    <div class="rc-head">
                        <span>{{ scorerTitle }}</span>
                        <span class="more" @click="router.push('/scorers')"
                            >All</span
                        >
                    </div>
                    <div class="rc-body" style="padding: 4px 6px">
                        <div
                            v-for="(s, i) in topScorers"
                            :key="s.player?.id ?? i"
                            class="rc-scorer"
                            role="button"
                            tabindex="0"
                            @click="
                                s.player?.id &&
                                router.push(`/player/${s.player.id}`)
                            "
                            @keydown.enter="
                                s.player?.id &&
                                router.push(`/player/${s.player.id}`)
                            "
                        >
                            <span
                                class="display tnum"
                                :style="{
                                    width: '18px',
                                    fontWeight: 800,
                                    color:
                                        i === 0
                                            ? 'var(--accent)'
                                            : 'var(--text-muted)',
                                }"
                                >{{ i + 1 }}</span
                            >
                            <Crest :team="s.team" :size="20" />
                            <span
                                style="
                                    flex: 1;
                                    font-size: 13px;
                                    font-weight: 600;
                                "
                                >{{ s.player?.name }}</span
                            >
                            <span
                                class="display tnum"
                                style="font-weight: 800; font-size: 16px"
                                >{{ s.goals }}</span
                            >
                        </div>

                        <!-- Pre-tournament: no goals scored yet. -->
                        <div v-if="!topScorers.length" class="rc-empty">
                            <span class="display tnum">0</span>
                            <div>
                                No goals yet<template v-if="wcStartLabel"
                                    ><br />World Cup starts
                                    {{ wcStartLabel }}</template
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top of the table -->
                <div v-if="tableRows.length" class="pp-rail-card">
                    <div class="rc-head">
                        <span>Top of the table</span>
                        <span
                            class="more"
                            @click="router.push('/competition/PL')"
                            >Premier League</span
                        >
                    </div>
                    <div class="rc-body">
                        <StandingsTable
                            :rows="tableRows"
                            compact
                            @team="(id) => id && router.push(`/team/${id}`)"
                        />
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>

<style scoped>
.rc-scorer {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    border-radius: var(--r-sm);
    cursor: pointer;
    transition: background var(--dur-fast);
}
.rc-scorer:hover {
    background: var(--hover);
}
.rc-empty {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px 12px;
    color: var(--text-muted);
    font-size: 13px;
}
.rc-empty .tnum {
    font-size: 34px;
    font-weight: 800;
    color: var(--text-faint);
    line-height: 1;
}
</style>
