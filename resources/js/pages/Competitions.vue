<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';

import { IcArrowR, IcGlobe, IcTrophy } from '@/components/icons';
import LivePulseBadge from '@/components/LivePulseBadge.vue';
import EmptyState from '@/components/states/EmptyState.vue';
import ErrorState from '@/components/states/ErrorState.vue';
import Skeleton from '@/components/states/Skeleton.vue';
import { useCompetitions } from '@/composables/useCompetitions';
import { FEATURED } from '@/lib/featured';
import { useMatchesStore } from '@/stores/matches';

const router = useRouter();
const matches = useMatchesStore();

const { data: competitions, loading, error, reload } = useCompetitions();

const HERO_CODE = 'WC';
const code = (c) => c.code || c.id;

// The World Cup gets a highlighted hero at the top of the page.
const hero = computed(
    () => (competitions.value ?? []).find((c) => code(c) === HERO_CODE) ?? null,
);

// Everything else follows, marquee competitions first (Champions League,
// Premier League, …) then the remainder in their original order.
const rest = computed(() => {
    const rank = (c) => {
        const i = FEATURED.indexOf(code(c));

        return i === -1 ? FEATURED.length : i;
    };

    return (competitions.value ?? [])
        .filter((c) => code(c) !== HERO_CODE)
        .sort((a, b) => rank(a) - rank(b));
});

const liveCount = (c) =>
    matches.live.filter(
        (m) =>
            m.competition?.code === c.code ||
            String(m.competition?.id) === String(c.id),
    ).length;

const open = (c) => router.push(`/competition/${code(c)}`);
</script>

<template>
    <div class="pp-page pp-rise">
        <div class="pp-pagehead">
            <div>
                <h1>Competitions</h1>
                <div class="ph-sub">
                    Leagues, cups &amp; international tournaments
                </div>
            </div>
        </div>

        <div v-if="loading" class="pp-grid cols-3">
            <Skeleton v-for="i in 6" :key="i" :h="130" :r="16" />
        </div>

        <ErrorState v-else-if="error" @retry="reload" />

        <EmptyState v-else-if="!competitions?.length" title="No competitions" />

        <template v-else>
            <!-- World Cup spotlight -->
            <div
                v-if="hero"
                class="pp-spotlight comp-hero"
                role="button"
                tabindex="0"
                @click="open(hero)"
                @keydown.enter="open(hero)"
                @keydown.space.prevent="open(hero)"
            >
                <div class="sp-inner">
                    <div>
                        <div class="sp-tag">
                            <IcTrophy :size="14" /> Featured Competition
                        </div>
                        <h2>{{ hero.name }}</h2>
                        <div class="sp-meta">
                            <IcGlobe
                                :size="13"
                                style="vertical-align: -2px; margin-right: 5px"
                            />{{ hero.region }} · Summer 2026
                        </div>
                    </div>
                    <div class="sp-stats">
                        <LivePulseBadge
                            v-if="liveCount(hero)"
                            :label="`${liveCount(hero)} LIVE`"
                            small
                        />
                        <button
                            class="pp-btn primary"
                            type="button"
                            @click.stop="open(hero)"
                        >
                            Explore <IcArrowR :size="16" />
                        </button>
                    </div>
                </div>
            </div>

            <div class="pp-grid cols-3">
                <div
                    v-for="c in rest"
                    :key="c.id"
                    class="pp-comptile"
                    role="button"
                    tabindex="0"
                    @click="open(c)"
                    @keydown.enter="open(c)"
                    @keydown.space.prevent="open(c)"
                >
                    <span v-if="liveCount(c)" class="ct-live"
                        ><LivePulseBadge :label="`${liveCount(c)} LIVE`" small
                    /></span>
                    <div
                        class="ct-logo"
                        :style="{
                            background: `linear-gradient(135deg, ${c.color}, ${c.color}cc)`,
                        }"
                    >
                        <IcTrophy v-if="c.kind === 'cup'" :size="24" />
                        <span v-else style="font-size: 18px">{{
                            (c.short || c.name)[0]
                        }}</span>
                    </div>
                    <h3>{{ c.name }}</h3>
                    <div class="ct-region">
                        <IcGlobe
                            :size="12"
                            style="vertical-align: -2px; margin-right: 4px"
                        />{{ c.region }}
                    </div>
                    <span class="ct-arrow"><IcArrowR :size="18" /></span>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
/* Lift the spotlight into a clickable, accent-ringed hero on this page. */
.comp-hero {
    cursor: pointer;
    border-color: color-mix(in srgb, var(--accent) 45%, var(--border));
    transition:
        transform var(--dur-fast),
        border-color var(--dur-fast),
        box-shadow var(--dur-fast);
}
.comp-hero:hover {
    transform: translateY(-2px);
    border-color: var(--accent);
    box-shadow: var(--shadow-md);
}
.comp-hero .sp-stats {
    align-items: center;
    gap: 16px;
}
</style>
