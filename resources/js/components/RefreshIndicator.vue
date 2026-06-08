<script setup>
import { useIntervalFn } from '@vueuse/core';
import { computed, ref, toRef, watch } from 'vue';

import { useUpdatedAgo } from '@/composables/useUpdatedAgo';

const props = defineProps({
    seconds: { type: Number, default: 15 },
    paused: { type: Boolean, default: false },
    lastUpdated: { type: String, default: null },
});

const R = 9;
const CIRC = 2 * Math.PI * R;

const remaining = ref(props.seconds);
const offset = computed(
    () => CIRC * (1 - remaining.value / Math.max(1, props.seconds)),
);
const updatedAgo = useUpdatedAgo(toRef(props, 'lastUpdated'));

const { pause, resume } = useIntervalFn(
    () => {
        remaining.value =
            remaining.value <= 1 ? props.seconds : remaining.value - 1;
    },
    1000,
    { immediate: !props.paused },
);

watch(
    () => props.paused,
    (isPaused) => (isPaused ? pause() : resume()),
);

// Reset the ring whenever a real poll lands.
watch(
    () => props.lastUpdated,
    () => (remaining.value = props.seconds),
);
</script>

<template>
    <span
        class="refresh"
        :title="paused ? 'Auto-refresh paused' : `Updating in ${remaining}s`"
    >
        <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true">
            <circle
                cx="12"
                cy="12"
                :r="R"
                fill="none"
                stroke="var(--border-strong)"
                stroke-width="2.2"
            />
            <circle
                cx="12"
                cy="12"
                :r="R"
                fill="none"
                stroke="var(--accent)"
                stroke-width="2.2"
                :stroke-dasharray="CIRC"
                :stroke-dashoffset="offset"
                stroke-linecap="round"
                transform="rotate(-90 12 12)"
                style="transition: stroke-dashoffset 1s linear"
            />
        </svg>
        <span v-if="updatedAgo" class="refresh-ago mono">{{ updatedAgo }}</span>
    </span>
</template>

<style scoped>
.refresh {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex: none;
}

.refresh-ago {
    font-size: 10px;
    font-weight: 600;
    color: var(--text-muted);
    white-space: nowrap;
}
</style>
