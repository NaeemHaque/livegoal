<script setup>
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

import Crest from '@/components/Crest.vue';
import FavoriteStar from '@/components/FavoriteStar.vue';
import { IcLive, IcStar, IcTrophy } from '@/components/icons';
import MatchCard from '@/components/MatchCard.vue';
import CornerFlag from '@/components/states/CornerFlag.vue';
import StateBlock from '@/components/states/StateBlock.vue';
import { useCompetitions } from '@/composables/useCompetitions';
import { compKey } from '@/lib/featured';
import api from '@/services/api';
import { useFavoritesStore } from '@/stores/favorites';
import { useMatchesStore } from '@/stores/matches';

const router = useRouter();
const favorites = useFavoritesStore();
const matches = useMatchesStore();
const { data: allComps } = useCompetitions();

// Resolve followed team ids -> team objects (crest + name) via cached /teams/{id}.
// Only the not-yet-resolved ids are fetched; a token guards against an older
// batch overwriting a newer one under rapid (un)following.
const resolved = ref([]);
let resolveToken = 0;
watch(
    () => favorites.teamIds,
    async (ids) => {
        const mine = ++resolveToken;
        const known = new Set(resolved.value.map((t) => String(t.id)));
        const missing = ids.filter((id) => !known.has(String(id)));

        if (!missing.length) {
            return;
        }

        const fetched = await Promise.all(
            missing.map((id) =>
                api
                    .get(`/teams/${id}`)
                    .then((r) => r.data?.data)
                    .catch(() => null),
            ),
        );

        if (mine === resolveToken) {
            resolved.value = [...resolved.value, ...fetched.filter(Boolean)];
        }
    },
    { immediate: true },
);

// Filter the resolved cache by the live id set so (un)following updates instantly.
const teams = computed(() =>
    resolved.value.filter((t) => favorites.teamIds.includes(String(t.id))),
);
const comps = computed(() =>
    (allComps.value ?? []).filter((c) =>
        favorites.competitionIds.includes(compKey(c)),
    ),
);
const favMatches = computed(() =>
    matches.live.filter((m) => favorites.isMatchFavorite(m)),
);

const hasAny = computed(
    () => favorites.teamIds.length > 0 || favorites.competitionIds.length > 0,
);

const openTeam = (id) => id && router.push(`/team/${id}`);
const openComp = (c) => router.push(`/competition/${c.code || c.id}`);
const openMatch = (m) => router.push(`/match/${m.id}`);
</script>

<template>
    <div class="pp-page pp-rise">
        <div class="pp-pagehead">
            <div>
                <h1>Following</h1>
                <div v-if="hasAny" class="ph-sub">
                    {{ favorites.teamIds.length }} teams ·
                    {{ favorites.competitionIds.length }} competitions
                </div>
            </div>
        </div>

        <StateBlock
            v-if="!hasAny"
            title="Nothing followed yet"
            text="Tap the star on any team or competition to pin it here for quick access."
            art
        >
            <template #icon><CornerFlag /></template>
            <template #action>
                <button
                    class="pp-btn primary"
                    type="button"
                    style="margin-top: 8px"
                    @click="router.push('/competitions')"
                >
                    Browse competitions
                </button>
            </template>
        </StateBlock>

        <template v-else>
            <!-- Live matches for followed teams -->
            <section v-if="favMatches.length" style="margin-bottom: 24px">
                <div class="pp-section-head">
                    <span class="sh-title"
                        ><IcLive :size="16" /> Their matches</span
                    ><span class="sh-line" />
                </div>
                <div class="pp-grid cols-2">
                    <MatchCard
                        v-for="m in favMatches"
                        :key="m.id"
                        :match="m"
                        :fav="true"
                        @open="openMatch"
                        @fav="favorites.toggleMatchFavorite(m)"
                    />
                </div>
            </section>

            <!-- Followed teams -->
            <section v-if="teams.length" style="margin-bottom: 24px">
                <div class="pp-section-head">
                    <span class="sh-title"
                        ><IcStar :size="15" style="color: var(--draw)" />
                        Teams</span
                    ><span class="sh-line" />
                </div>
                <div class="pp-grid cols-3">
                    <div
                        v-for="t in teams"
                        :key="t.id"
                        class="pp-playerrow"
                        role="button"
                        tabindex="0"
                        @click="openTeam(t.id)"
                        @keydown.enter="openTeam(t.id)"
                        @keydown.space.prevent="openTeam(t.id)"
                    >
                        <Crest :team="t" :size="32" />
                        <span class="pr-name">{{ t.name }}</span>
                        <span style="margin-left: auto">
                            <FavoriteStar
                                :active="true"
                                :label="`Unfollow ${t.name}`"
                                @toggle="favorites.toggle('team', t.id)"
                            />
                        </span>
                    </div>
                </div>
            </section>

            <!-- Followed competitions -->
            <section v-if="comps.length">
                <div class="pp-section-head">
                    <span class="sh-title"
                        ><IcTrophy :size="15" /> Competitions</span
                    ><span class="sh-line" />
                </div>
                <div class="pp-grid cols-3">
                    <div
                        v-for="c in comps"
                        :key="c.id"
                        class="pp-playerrow"
                        role="button"
                        tabindex="0"
                        @click="openComp(c)"
                        @keydown.enter="openComp(c)"
                        @keydown.space.prevent="openComp(c)"
                    >
                        <span
                            class="fc-tile"
                            :style="{
                                background: `linear-gradient(135deg, ${c.color}, ${c.color}cc)`,
                            }"
                        >
                            <IcTrophy :size="15" />
                        </span>
                        <span class="pr-name">{{ c.name }}</span>
                        <span style="margin-left: auto">
                            <FavoriteStar
                                :active="true"
                                :label="`Unfollow ${c.name}`"
                                @toggle="
                                    favorites.toggle('competition', compKey(c))
                                "
                            />
                        </span>
                    </div>
                </div>
            </section>
        </template>
    </div>
</template>

<style scoped>
.fc-tile {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    color: #fff;
    flex: none;
}
</style>
