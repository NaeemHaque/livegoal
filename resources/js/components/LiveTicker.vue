<script setup>
import { computed } from 'vue';

const props = defineProps({
    matches: { type: Array, default: () => [] },
});

// Doubled so the marquee can loop seamlessly.
const track = computed(() => [...props.matches, ...props.matches]);

const minuteLabel = (m) => {
    if (m.status === 'HT') {
        return 'HT';
    }

    return m.minute != null ? `${m.minute}'` : '';
};
</script>

<template>
    <div v-if="matches.length" class="pp-ticker">
        <span class="tk-label"><span class="dot" /> LIVE</span>
        <div class="tk-scroll">
            <div class="tk-track">
                <RouterLink
                    v-for="(m, i) in track"
                    :key="`${m.id}-${i < matches.length ? 'a' : 'b'}`"
                    :to="`/match/${m.id}`"
                    class="tk-item"
                >
                    <span class="tk-comp">{{ m.competition?.short }}</span>
                    <span class="tk-teams">
                        {{ m.home?.short }}
                        <span class="tk-sc"
                            >{{ m.homeScore ?? 0 }}–{{ m.awayScore ?? 0 }}</span
                        >
                        {{ m.away?.short }}
                    </span>
                    <span class="tk-min mono">{{ minuteLabel(m) }}</span>
                </RouterLink>
            </div>
        </div>
    </div>
</template>
