<script setup>
import { computed } from 'vue';

import Crest from '@/components/Crest.vue';
import { IcBall, IcWhistle } from '@/components/icons';

/**
 * Vertical match-events timeline (center spine, home events left, away events
 * right). Events are self-built by the backend poller from score changes —
 * the free data tier has no event feed, so there are no player names.
 */
const props = defineProps({
    match: { type: Object, required: true },
    events: { type: Array, default: () => [] },
});

// Newest first — latest event on top, kick-off marker at the bottom.
const rows = computed(() =>
    props.events
        .filter((ev) => ev.type === 'GOAL' || ev.type === 'HT')
        .reverse(),
);

const isFinished = computed(() => props.match.status === 'FT');

const scoreline = (home, away) =>
    home != null && away != null ? `${home}–${away}` : null;

const team = (side) => (side === 'away' ? props.match.away : props.match.home);

const teamName = (side) => {
    const t = team(side);

    return t?.name || t?.short || t?.tla || 'Goal';
};
</script>

<template>
    <div class="pp-timeline">
        <div v-if="isFinished" class="pp-tl-mid">
            <span class="tl-line" />
            <span class="tl-chip">
                <IcWhistle :size="13" /> Full-time
                <template v-if="scoreline(match.homeScore, match.awayScore)">
                    · {{ scoreline(match.homeScore, match.awayScore) }}
                </template>
            </span>
            <span class="tl-line" />
        </div>

        <template v-for="(ev, i) in rows" :key="`${ev.type}-${i}`">
            <div v-if="ev.type === 'HT'" class="pp-tl-mid">
                <span class="tl-line" />
                <span class="tl-chip">
                    <IcWhistle :size="13" /> Half-time
                    <template v-if="scoreline(ev.homeScore, ev.awayScore)">
                        · {{ scoreline(ev.homeScore, ev.awayScore) }}
                    </template>
                </span>
                <span class="tl-line" />
            </div>

            <div v-else class="pp-tl-row goal">
                <div
                    class="tl-side"
                    :class="ev.side === 'away' ? 'away' : 'home'"
                >
                    <div class="tl-card">
                        <span class="tl-ic goal"><IcBall :size="17" /></span>
                        <Crest :team="team(ev.side)" :size="22" />
                        <span class="tl-txt">
                            <span class="tl-player">{{
                                teamName(ev.side)
                            }}</span>
                            <span class="tl-detail">
                                Goal<template
                                    v-if="scoreline(ev.homeScore, ev.awayScore)"
                                >
                                    ·
                                    <b class="tnum">{{
                                        scoreline(ev.homeScore, ev.awayScore)
                                    }}</b></template
                                >
                            </span>
                        </span>
                    </div>
                </div>
                <div class="tl-min">
                    <span class="mono">{{
                        ev.minute != null ? `${ev.minute}'` : '–'
                    }}</span>
                </div>
            </div>
        </template>

        <div class="pp-tl-mid">
            <span class="tl-line" />
            <span class="tl-chip"><IcWhistle :size="13" /> Kick-off</span>
            <span class="tl-line" />
        </div>
    </div>
</template>
