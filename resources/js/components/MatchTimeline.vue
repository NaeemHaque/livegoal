<script setup>
import { computed } from 'vue';

import Crest from '@/components/Crest.vue';
import { IcBall, IcRefresh, IcWhistle } from '@/components/icons';

/**
 * Vertical match-events timeline (center spine, home events left, away events
 * right). Events come from ESPN (rich: scorer, assist, cards, subs with official
 * minutes) when available, otherwise the backend poller's self-built events
 * (goals/HT only, no player names) — the component renders both shapes.
 */
const props = defineProps({
    match: { type: Object, required: true },
    events: { type: Array, default: () => [] },
});

const GOAL_TYPES = ['GOAL', 'OWN_GOAL'];
const SIDE_TYPES = [...GOAL_TYPES, 'YELLOW_CARD', 'RED_CARD', 'SUBSTITUTION'];

// Newest first — latest event on top, kick-off marker at the bottom. Half-time
// renders inline as a center chip; everything else is a left/right side card.
const rows = computed(() =>
    props.events
        .filter((ev) => SIDE_TYPES.includes(ev.type) || ev.type === 'HT')
        .reverse(),
);

const isFinished = computed(() => props.match.status === 'FT');

const scoreline = (home, away) =>
    home != null && away != null ? `${home}–${away}` : null;

const team = (side) => (side === 'away' ? props.match.away : props.match.home);

const teamName = (side) => {
    const t = team(side);

    return t?.name || t?.short || t?.tla || '';
};

const isGoal = (ev) => GOAL_TYPES.includes(ev.type);

const rowClass = (ev) =>
    isGoal(ev) ? 'goal' : ev.type === 'SUBSTITUTION' ? 'sub' : 'card';

// The minute label prefers ESPN's display clock ("45'+2'") and falls back to
// the bare inferred minute.
const minuteLabel = (ev) =>
    ev.clock || (ev.minute != null ? `${ev.minute}'` : '–');

// Headline name: the involved player (ESPN) or the team (inferred fallback).
const primary = (ev) => ev.player || teamName(ev.side);

const detail = (ev) => {
    if (isGoal(ev)) {
        const parts = [ev.type === 'OWN_GOAL' ? 'Own goal' : 'Goal'];
        const score = scoreline(ev.homeScore, ev.awayScore);

        if (score) {
            parts.push(score);
        }

        if (ev.assist) {
            parts.push(`assist ${ev.assist}`);
        }

        return parts.join(' · ');
    }

    return {
        YELLOW_CARD: 'Yellow card',
        RED_CARD: 'Red card',
        SUBSTITUTION: 'Substitution',
    }[ev.type];
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

            <div v-else class="pp-tl-row" :class="rowClass(ev)">
                <div
                    class="tl-side"
                    :class="ev.side === 'away' ? 'away' : 'home'"
                >
                    <div class="tl-card">
                        <span v-if="isGoal(ev)" class="tl-ic goal"
                            ><IcBall :size="17"
                        /></span>
                        <span
                            v-else-if="ev.type === 'YELLOW_CARD'"
                            class="tl-card-badge yellow"
                            aria-label="Yellow card"
                        />
                        <span
                            v-else-if="ev.type === 'RED_CARD'"
                            class="tl-card-badge red"
                            aria-label="Red card"
                        />
                        <span v-else class="tl-ic sub"
                            ><IcRefresh :size="15"
                        /></span>
                        <Crest :team="team(ev.side)" :size="22" />
                        <span class="tl-txt">
                            <span class="tl-player">{{ primary(ev) }}</span>
                            <span class="tl-detail">{{ detail(ev) }}</span>
                        </span>
                    </div>
                </div>
                <div class="tl-min">
                    <span class="mono">{{ minuteLabel(ev) }}</span>
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
