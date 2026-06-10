<script setup>
import { computed, ref, watch } from 'vue';

import { IcTrophy } from '@/components/icons';

const props = defineProps({
    competition: { type: Object, default: null },
    size: { type: Number, default: 48 },
});

const failed = ref(false);

// Reset the broken-image fallback when the emblem changes (recycled nodes).
watch(
    () => props.competition?.emblem,
    () => (failed.value = false),
);

const showImg = computed(
    () => Boolean(props.competition?.emblem) && !failed.value,
);
const iconSize = computed(() => Math.round(props.size * 0.5));
const monoSize = computed(() => `${Math.round(props.size * 0.4)}px`);
</script>

<template>
    <span
        class="pp-complogo"
        :class="{ img: showImg }"
        :style="{
            width: `${size}px`,
            height: `${size}px`,
            background: showImg
                ? undefined
                : `linear-gradient(135deg, ${competition?.color}, ${competition?.color}cc)`,
        }"
        :title="competition?.name"
    >
        <img
            v-if="showImg"
            :src="competition.emblem"
            :alt="competition?.name"
            loading="lazy"
            @error="failed = true"
        />
        <IcTrophy v-else-if="competition?.kind === 'cup'" :size="iconSize" />
        <span v-else :style="{ fontSize: monoSize }">{{
            (competition?.short || competition?.name || '?')[0]
        }}</span>
    </span>
</template>

<style scoped>
.pp-complogo {
    display: grid;
    place-items: center;
    border-radius: 12px;
    color: #fff;
    font-family: var(--font-display);
    font-weight: 800;
    flex: none;
    overflow: hidden;
}
/* Real emblem: a white chip so dark/monochrome crests (Champions League,
   Premier League, …) stay legible on the dark UI. */
.pp-complogo.img {
    background: #fff;
    padding: 7px;
}
.pp-complogo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
</style>
