<script setup>
import { useRouter } from 'vue-router';

import { IcArrowR, IcGlobe, IcTrophy } from '@/components/icons';
import LivePulseBadge from '@/components/LivePulseBadge.vue';
import EmptyState from '@/components/states/EmptyState.vue';
import ErrorState from '@/components/states/ErrorState.vue';
import Skeleton from '@/components/states/Skeleton.vue';
import { useCompetitions } from '@/composables/useCompetitions';
import { useMatchesStore } from '@/stores/matches';

const router = useRouter();
const matches = useMatchesStore();

const { data: competitions, loading, error, reload } = useCompetitions();

const liveCount = (c) =>
    matches.live.filter(
        (m) =>
            m.competition?.code === c.code ||
            String(m.competition?.id) === String(c.id),
    ).length;

const open = (c) => router.push(`/competition/${c.code || c.id}`);
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

        <div v-else class="pp-grid cols-3">
            <div
                v-for="c in competitions"
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
    </div>
</template>
