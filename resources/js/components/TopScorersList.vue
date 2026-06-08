<script setup>
import Crest from '@/components/Crest.vue';

defineProps({
    scorers: { type: Array, default: () => [] },
    compact: { type: Boolean, default: false },
});

const emit = defineEmits(['player', 'team']);
</script>

<template>
    <div style="display: flex; flex-direction: column; gap: 8px">
        <div
            v-for="(s, i) in scorers"
            :key="s.player?.id ?? i"
            class="pp-scorer"
            :class="{ top: i === 0 }"
            style="cursor: pointer"
            @click="emit('player', s.player?.id)"
        >
            <span class="sc-rank">{{ s.rank ?? i + 1 }}</span>
            <div class="sc-player">
                <Crest :team="s.team" :size="26" />
                <div>
                    <div class="nm">{{ s.player?.name }}</div>
                    <div class="tm">{{ s.team?.name }}</div>
                </div>
            </div>
            <div class="sc-stats">
                <template v-if="!compact">
                    <div>
                        <div class="sc-sub mono">{{ s.assists ?? 0 }}</div>
                        <div class="sc-sub">AST</div>
                    </div>
                    <div>
                        <div class="sc-sub mono">{{ s.penalties ?? 0 }}</div>
                        <div class="sc-sub">PEN</div>
                    </div>
                </template>
                <div style="text-align: center">
                    <span class="sc-goals">{{ s.goals }}</span>
                    <div class="sc-sub">GOALS</div>
                </div>
            </div>
        </div>
    </div>
</template>
