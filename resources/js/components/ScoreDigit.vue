<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    value: { type: [Number, String], default: 0 },
    size: { type: Number, default: 44 },
});

const flip = ref(false);

watch(
    () => props.value,
    () => {
        flip.value = true;
        setTimeout(() => (flip.value = false), 420);
    },
);
</script>

<template>
    <span
        class="pp-digit display tnum"
        :class="{ flip }"
        :style="{ fontSize: `${size}px` }"
        >{{ value }}</span
    >
</template>
