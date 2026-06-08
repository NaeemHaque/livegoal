<script setup>
import { computed } from 'vue';

import ScoreDigit from '@/components/ScoreDigit.vue';

const props = defineProps({
    match: { type: Object, required: true },
    size: { type: Number, default: 44 },
});

const live = computed(() =>
    ['LIVE', 'HT', 'ET', 'PEN'].includes(props.match.status),
);
const show = computed(
    () => !['SCHEDULED', 'POSTPONED'].includes(props.match.status),
);
</script>

<template>
    <span
        v-if="!show"
        class="pp-score vs display"
        :style="{ fontSize: `${size * 0.5}px` }"
        >vs</span
    >
    <span
        v-else
        class="pp-score display"
        :class="{ live }"
        :style="{ fontSize: `${size}px` }"
    >
        <ScoreDigit :value="match.homeScore ?? 0" :size="size" />
        <span class="sep" :style="{ fontSize: `${size * 0.7}px` }">–</span>
        <ScoreDigit :value="match.awayScore ?? 0" :size="size" />
    </span>
</template>
