<script setup>
import { computed } from 'vue';

import Crest from '@/components/Crest.vue';
import FavoriteStar from '@/components/FavoriteStar.vue';
import { IcPin, IcWhistle } from '@/components/icons';
import MatchStatus from '@/components/MatchStatus.vue';
import ScoreDisplay from '@/components/ScoreDisplay.vue';

const props = defineProps({
    match: { type: Object, required: true },
    expanded: { type: Boolean, default: false },
    fav: { type: Boolean, default: false },
    favable: { type: Boolean, default: true },
    scored: { type: Boolean, default: false },
    showDate: { type: Boolean, default: false },
});

const emit = defineEmits(['open', 'fav']);

const m = computed(() => props.match);
const live = computed(() =>
    ['LIVE', 'HT', 'ET', 'PEN'].includes(m.value.status),
);
const winner = computed(() => {
    if (m.value.status !== 'FT') {
        return null;
    }

    const h = m.value.homeScore ?? 0;
    const a = m.value.awayScore ?? 0;

    return h > a ? 'home' : a > h ? 'away' : null;
});

const open = () => emit('open', m.value);
</script>

<template>
    <article
        class="pp-matchcard"
        :class="{ expanded, 'is-live': live, scored }"
        role="button"
        tabindex="0"
        :aria-label="`${m.home?.name} ${m.homeScore ?? ''} ${m.away?.name} ${m.awayScore ?? ''}, ${m.status}`"
        @click="open"
        @keydown.enter="open"
        @keydown.space.prevent="open"
    >
        <div class="mc-top">
            <span class="mc-comp">
                <span
                    class="dot"
                    :style="{ background: m.competition?.color }"
                />
                {{ m.competition?.short
                }}<template v-if="m.group"> · {{ m.group }}</template>
            </span>
            <div class="mc-top-r">
                <MatchStatus :match="m" small :show-date="showDate" />
                <FavoriteStar
                    v-if="favable"
                    :active="fav"
                    :size="16"
                    @toggle="emit('fav')"
                />
            </div>
        </div>

        <div class="mc-body">
            <div
                class="mc-team"
                :class="{ win: winner === 'home', lose: winner === 'away' }"
            >
                <Crest :team="m.home" :size="expanded ? 28 : 26" />
                <span class="t-name">{{
                    expanded ? m.home?.name : m.home?.short
                }}</span>
            </div>
            <div class="mc-score">
                <ScoreDisplay :match="m" :size="expanded ? 34 : 30" />
            </div>
            <div
                class="mc-team rev"
                :class="{ win: winner === 'away', lose: winner === 'home' }"
            >
                <span class="t-name">{{
                    expanded ? m.away?.name : m.away?.short
                }}</span>
                <Crest :team="m.away" :size="expanded ? 28 : 26" />
            </div>
        </div>

        <div v-if="expanded && (m.venue || m.referee)" class="mc-meta">
            <span v-if="m.venue"><IcPin :size="13" />{{ m.venue }}</span>
            <span v-if="m.referee"
                ><IcWhistle :size="13" />{{ m.referee }}</span
            >
        </div>

        <span v-if="live" class="mc-liveline" aria-hidden="true" />
    </article>
</template>
