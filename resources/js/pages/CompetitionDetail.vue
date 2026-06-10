<script setup>
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

import Bracket from '@/components/Bracket.vue';
import Crest from '@/components/Crest.vue';
import GroupCard from '@/components/GroupCard.vue';
import { IcChevL, IcGlobe, IcStar, IcTrophy } from '@/components/icons';
import InlineLoader from '@/components/InlineLoader.vue';
import MatchCard from '@/components/MatchCard.vue';
import SectionHead from '@/components/SectionHead.vue';
import StandingsTable from '@/components/StandingsTable.vue';
import EmptyState from '@/components/states/EmptyState.vue';
import TopScorersList from '@/components/TopScorersList.vue';
import { useApi } from '@/composables/useApi';
import { useCompetition } from '@/composables/useCompetition';
import { useScorers } from '@/composables/useScorers';
import { useStandings } from '@/composables/useStandings';
import { useTimeFormat } from '@/composables/useTimeFormat';
import { buildKnockoutRounds } from '@/lib/bracket';
import { compKey } from '@/lib/featured';
import { useFavoritesStore } from '@/stores/favorites';

const props = defineProps({ id: { type: String, required: true } });
const router = useRouter();
const favorites = useFavoritesStore();
const time = useTimeFormat();

const id = computed(() => props.id);
const { data: competition } = useCompetition(id);
const { data: standings, loading: standingsLoading } = useStandings(id);
const { data: scorers, loading: scorersLoading } = useScorers(id);
const { data: teams } = useApi(() => `/competitions/${id.value}/teams`);
const { data: seasonMatches } = useApi(
    () => `/competitions/${id.value}/matches`,
);

const groups = computed(() => standings.value?.groups ?? []);
const hasGroups = computed(() => groups.value.length > 1);

const matches = computed(() => seasonMatches.value ?? []);
const rounds = computed(() => buildKnockoutRounds(matches.value));
const hasKnockout = computed(() => rounds.value.length > 0);
const fixtures = computed(() =>
    matches.value
        .filter((m) => m.status !== 'FT')
        .sort((a, b) =>
            String(a.kickoff ?? '').localeCompare(String(b.kickoff ?? '')),
        ),
);
const results = computed(() =>
    matches.value
        .filter((m) => m.status === 'FT')
        .sort((a, b) =>
            String(b.kickoff ?? '').localeCompare(String(a.kickoff ?? '')),
        ),
);

// Segment a kickoff-sorted list into day groups ("12 July 2026") so Fixtures and
// Results read like the homepage's grouped sections instead of a flat grid.
function groupByDay(list) {
    const days = [];
    let current = null;

    for (const m of list) {
        const label = time.longDate(m.kickoff);

        if (!current || current.label !== label) {
            current = { label, matches: [] };
            days.push(current);
        }

        current.matches.push(m);
    }

    return days;
}

const dateGroups = computed(() =>
    groupByDay(tab.value === 'fixtures' ? fixtures.value : results.value),
);

const tabs = computed(() => [
    hasGroups.value
        ? { id: 'groups', label: 'Groups' }
        : { id: 'table', label: 'Standings' },
    { id: 'fixtures', label: 'Fixtures' },
    ...(hasKnockout.value ? [{ id: 'knockout', label: 'Knockout' }] : []),
    { id: 'results', label: 'Results' },
    { id: 'scorers', label: 'Top Scorers' },
    { id: 'teams', label: 'Teams' },
]);

const tab = ref('table');
watch(hasGroups, (g) => (tab.value = g ? 'groups' : 'table'), {
    immediate: true,
});

const favKey = computed(() => compKey(competition.value) || String(id.value));
const isFav = computed(() => favorites.isFavorite('competition', favKey.value));
const toggleFav = () => favorites.toggle('competition', favKey.value);

const openTeam = (teamId) => teamId && router.push(`/team/${teamId}`);
const openMatch = (m) => router.push(`/match/${m.id}`);
</script>

<template>
    <div class="pp-page pp-page-wide pp-rise">
        <button
            class="pp-btn ghost sm"
            type="button"
            style="margin-bottom: 14px"
            @click="router.push('/competitions')"
        >
            <IcChevL :size="15" /> Competitions
        </button>

        <div class="pp-entity-head">
            <div
                class="eh-glow"
                :style="{
                    background: `radial-gradient(100% 140% at 0% 0%, ${competition?.color || 'var(--accent)'}, transparent 60%)`,
                }"
            />
            <div class="eh-main">
                <div
                    class="ct-logo"
                    style="width: 64px; height: 64px; margin: 0"
                    :style="{
                        background: `linear-gradient(135deg, ${competition?.color}, ${competition?.color}cc)`,
                    }"
                >
                    <IcTrophy v-if="competition?.kind === 'cup'" :size="30" />
                    <span
                        v-else
                        style="
                            font-size: 24px;
                            font-family: var(--font-display);
                        "
                        >{{ (competition?.short || '?')[0] }}</span
                    >
                </div>
                <div>
                    <h1>{{ competition?.name ?? '…' }}</h1>
                    <div class="eh-sub">
                        <span
                            ><IcGlobe :size="13" />
                            {{ competition?.region }}</span
                        >
                    </div>
                </div>
            </div>
            <div class="eh-stats">
                <button
                    class="pp-btn"
                    :class="isFav ? 'ghost' : 'primary'"
                    type="button"
                    :aria-pressed="isFav"
                    @click="toggleFav"
                >
                    <IcStar :size="16" /> {{ isFav ? 'Following' : 'Follow' }}
                </button>
            </div>
        </div>

        <div class="pp-tabs" style="margin-bottom: 18px">
            <button
                v-for="t in tabs"
                :key="t.id"
                class="tab"
                :class="{ on: tab === t.id }"
                type="button"
                @click="tab = t.id"
            >
                {{ t.label }}
            </button>
        </div>

        <!-- Groups -->
        <div v-if="tab === 'groups'" class="pp-grid groups">
            <GroupCard
                v-for="g in groups"
                :key="g.key"
                :label="g.label"
                :rows="g.rows"
                @team="openTeam"
            />
        </div>

        <!-- League table -->
        <div v-else-if="tab === 'table'" class="pp-panel">
            <InlineLoader
                v-if="standingsLoading"
                label="Loading table"
                :min-height="240"
            />
            <EmptyState
                v-else-if="!groups[0]?.rows?.length"
                title="No table available"
            />
            <StandingsTable v-else :rows="groups[0].rows" @team="openTeam" />
        </div>

        <!-- Knockout bracket -->
        <div v-else-if="tab === 'knockout'" class="pp-bracket-scroll">
            <Bracket
                :rounds="rounds"
                @open="(matchId) => router.push(`/match/${matchId}`)"
            />
        </div>

        <!-- Fixtures / Results — segmented into day groups -->
        <template v-else-if="tab === 'fixtures' || tab === 'results'">
            <template v-if="dateGroups.length">
                <div
                    v-for="group in dateGroups"
                    :key="group.label"
                    style="margin-bottom: 18px"
                >
                    <SectionHead
                        :title="group.label"
                        :count="group.matches.length"
                    />
                    <div class="pp-grid cols-2">
                        <MatchCard
                            v-for="m in group.matches"
                            :key="m.id"
                            :match="m"
                            @open="openMatch"
                        />
                    </div>
                </div>
            </template>
            <EmptyState
                v-else
                :title="
                    tab === 'fixtures'
                        ? 'No upcoming fixtures'
                        : 'No results yet'
                "
            />
        </template>

        <!-- Top scorers -->
        <template v-else-if="tab === 'scorers'">
            <InlineLoader
                v-if="scorersLoading"
                label="Loading scorers"
                :min-height="220"
            />
            <EmptyState
                v-else-if="!scorers?.length"
                title="No scorers yet"
                text="Top scorers appear once the competition is underway."
            />
            <TopScorersList
                v-else
                :scorers="scorers"
                @player="(pid) => pid && router.push(`/player/${pid}`)"
            />
        </template>

        <!-- Teams -->
        <div v-else-if="tab === 'teams'" class="pp-grid cols-4">
            <div
                v-for="t in teams ?? []"
                :key="t.id"
                class="pp-playerrow"
                role="button"
                tabindex="0"
                style="padding: 14px"
                @click="openTeam(t.id)"
                @keydown.enter="openTeam(t.id)"
                @keydown.space.prevent="openTeam(t.id)"
            >
                <Crest :team="t" :size="32" />
                <span class="pr-name">{{ t.name }}</span>
            </div>
        </div>
    </div>
</template>
