<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';

import Crest from '@/components/Crest.vue';
import FormGuide from '@/components/FormGuide.vue';
import { IcChevL, IcClock, IcGlobe, IcList, IcStar } from '@/components/icons';
import MatchCard from '@/components/MatchCard.vue';
import EmptyState from '@/components/states/EmptyState.vue';
import Skeleton from '@/components/states/Skeleton.vue';
import { useApi } from '@/composables/useApi';
import { useBack } from '@/composables/useBack';
import { useTeam } from '@/composables/useTeam';
import { useFavoritesStore } from '@/stores/favorites';

const props = defineProps({ id: { type: String, required: true } });
const router = useRouter();
const goBack = useBack();
const favorites = useFavoritesStore();

const id = computed(() => props.id);
const { data: team, loading } = useTeam(id);
const { data: teamMatches } = useApi(() => `/teams/${id.value}/matches`);

const tab = ref('overview');
const matches = computed(() => teamMatches.value ?? []);

const finished = computed(() =>
    matches.value
        .filter((m) => m.status === 'FT')
        .sort((a, b) =>
            String(b.kickoff ?? '').localeCompare(String(a.kickoff ?? '')),
        ),
);
const upcoming = computed(() =>
    matches.value
        .filter((m) => m.status !== 'FT')
        .sort((a, b) =>
            String(a.kickoff ?? '').localeCompare(String(b.kickoff ?? '')),
        ),
);

const outcome = (m) => {
    const home = String(m.home?.id) === String(id.value);
    const us = (home ? m.homeScore : m.awayScore) ?? 0;
    const them = (home ? m.awayScore : m.homeScore) ?? 0;

    return us > them ? 'W' : us < them ? 'L' : 'D';
};
const form = computed(() => finished.value.slice(0, 5).map(outcome).reverse());

const LINES = ['Goalkeepers', 'Defenders', 'Midfielders', 'Forwards', 'Squad'];
const lineOf = (position) => {
    const p = String(position ?? '').toLowerCase();

    if (p.includes('keeper')) {
        return 'Goalkeepers';
    }

    if (p.includes('back') || p.includes('defence') || p.includes('defender')) {
        return 'Defenders';
    }

    if (p.includes('midfield')) {
        return 'Midfielders';
    }

    if (
        p.includes('offence') ||
        p.includes('forward') ||
        p.includes('winger') ||
        p.includes('striker')
    ) {
        return 'Forwards';
    }

    return 'Squad';
};

const squadGroups = computed(() => {
    const groups = new Map();

    for (const p of team.value?.squad ?? []) {
        const line = lineOf(p.position);

        if (!groups.has(line)) {
            groups.set(line, []);
        }

        groups.get(line).push(p);
    }

    return LINES.filter((line) => groups.has(line)).map((position) => ({
        position,
        players: groups.get(position),
    }));
});

const fav = computed(() => favorites.isFavorite('team', id.value));
const toggleFav = () => favorites.toggle('team', id.value);
const openMatch = (m) => router.push(`/match/${m.id}`);

const TABS = [
    { id: 'overview', label: 'Overview' },
    { id: 'squad', label: 'Squad' },
    { id: 'fixtures', label: 'Fixtures' },
    { id: 'results', label: 'Results' },
];
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

        <div class="pp-entity-head">
            <div
                class="eh-glow"
                style="
                    background: radial-gradient(
                        100% 140% at 0% 0%,
                        var(--accent),
                        transparent 60%
                    );
                "
            />
            <div class="eh-main">
                <Crest :team="team" :size="72" />
                <div>
                    <h1>{{ team?.name ?? '…' }}</h1>
                    <div class="eh-sub">
                        <span
                            ><IcGlobe :size="13" />
                            {{ team?.area?.name || 'Club' }}</span
                        >
                        <span v-if="team?.venue">{{ team.venue }}</span>
                    </div>
                </div>
            </div>
            <div class="eh-stats">
                <button
                    class="pp-btn"
                    :class="fav ? 'ghost' : 'primary'"
                    type="button"
                    @click="toggleFav"
                >
                    <IcStar :size="16" /> {{ fav ? 'Following' : 'Follow' }}
                </button>
            </div>
        </div>

        <div class="pp-tabs" style="margin-bottom: 18px">
            <button
                v-for="t in TABS"
                :key="t.id"
                class="tab"
                :class="{ on: tab === t.id }"
                type="button"
                @click="tab = t.id"
            >
                {{ t.label }}
            </button>
        </div>

        <!-- Overview -->
        <div v-if="tab === 'overview'" class="pp-grid cols-2">
            <div>
                <div class="pp-section-head">
                    <span class="sh-title"
                        ><IcClock :size="16" /> Next match</span
                    ><span class="sh-line" />
                </div>
                <MatchCard
                    v-if="upcoming[0]"
                    :match="upcoming[0]"
                    expanded
                    @open="openMatch"
                />
                <EmptyState v-else title="No upcoming match" />
            </div>
            <div>
                <div class="pp-section-head">
                    <span class="sh-title"
                        ><IcList :size="16" /> Recent form</span
                    ><span class="sh-line" />
                </div>
                <div
                    class="pp-panel"
                    style="
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                    "
                >
                    <FormGuide v-if="form.length" :form="form" :size="26" />
                    <span v-else style="color: var(--text-muted)"
                        >No recent results</span
                    >
                    <span style="font-size: 12.5px; color: var(--text-muted)"
                        >Last 5</span
                    >
                </div>
                <div v-if="finished[0]" style="margin-top: 12px">
                    <MatchCard :match="finished[0]" @open="openMatch" />
                </div>
            </div>
        </div>

        <!-- Squad -->
        <div v-else-if="tab === 'squad'">
            <div v-if="loading"><Skeleton :h="200" :r="12" /></div>
            <EmptyState
                v-else-if="!squadGroups.length"
                title="Squad not available"
                text="The free data plan doesn't include this squad."
            />
            <div
                v-for="group in squadGroups"
                v-else
                :key="group.position"
                class="pp-posgroup"
            >
                <div class="pg-label">{{ group.position }}</div>
                <div class="pp-grid cols-3">
                    <div
                        v-for="p in group.players"
                        :key="p.id"
                        class="pp-playerrow"
                        role="button"
                        tabindex="0"
                        @click="router.push(`/player/${p.id}`)"
                        @keydown.enter="router.push(`/player/${p.id}`)"
                    >
                        <span class="pr-num tnum">{{
                            p.shirtNumber ?? '–'
                        }}</span>
                        <span class="pr-name">{{ p.name }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fixtures / Results -->
        <template v-else-if="tab === 'fixtures' || tab === 'results'">
            <div
                v-if="(tab === 'fixtures' ? upcoming : finished).length"
                class="pp-grid cols-2"
            >
                <MatchCard
                    v-for="m in tab === 'fixtures' ? upcoming : finished"
                    :key="m.id"
                    :match="m"
                    @open="openMatch"
                />
            </div>
            <EmptyState
                v-else
                :title="
                    tab === 'fixtures'
                        ? 'No upcoming matches'
                        : 'No results yet'
                "
            />
        </template>
    </div>
</template>
