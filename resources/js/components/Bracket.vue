<script setup>
import Crest from '@/components/Crest.vue';

defineProps({
    rounds: { type: Array, default: () => [] },
});

const emit = defineEmits(['open']);

const winner = (t) => {
    if (t.hs == null || t.as == null || t.hs === t.as) {
        return null;
    }

    return t.hs > t.as ? 'home' : 'away';
};
const label = (team) => team?.tla || team?.short || team?.name || 'TBD';
</script>

<template>
    <div class="pp-bracket">
        <div v-for="(rd, ri) in rounds" :key="ri" class="b-col">
            <div class="b-col-title">{{ rd.title }}</div>
            <div class="b-col-body">
                <div
                    v-for="(t, i) in rd.ties"
                    :key="t.id ?? i"
                    class="pp-bnode"
                    :class="{ live: t.live }"
                    :role="t.id ? 'button' : undefined"
                    :tabindex="t.id ? 0 : -1"
                    @click="t.id && emit('open', t.id)"
                    @keydown.enter="t.id && emit('open', t.id)"
                >
                    <div
                        class="bn-row"
                        :class="{
                            w: winner(t) === 'home',
                            l: winner(t) === 'away',
                        }"
                    >
                        <Crest v-if="t.home" :team="t.home" :size="18" />
                        <span class="bn-name" :class="{ tbd: !t.home }">{{
                            t.home ? label(t.home) : 'TBD'
                        }}</span>
                        <span class="bn-score tnum">{{ t.hs ?? '–' }}</span>
                    </div>
                    <div
                        class="bn-row"
                        :class="{
                            w: winner(t) === 'away',
                            l: winner(t) === 'home',
                        }"
                    >
                        <Crest v-if="t.away" :team="t.away" :size="18" />
                        <span class="bn-name" :class="{ tbd: !t.away }">{{
                            t.away ? label(t.away) : 'TBD'
                        }}</span>
                        <span class="bn-score tnum">{{ t.as ?? '–' }}</span>
                    </div>
                    <span v-if="t.live" class="bn-live"
                        ><span class="d" />LIVE</span
                    >
                </div>
            </div>
        </div>
    </div>
</template>
