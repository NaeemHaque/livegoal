<script setup>
defineProps({
    form: { type: Array, default: () => [] }, // ['W','D','L', ...]
    size: { type: Number, default: 20 },
});

const titleFor = (r) => ({ W: 'Win', D: 'Draw', L: 'Loss' })[r] || r;
</script>

<template>
    <span class="pp-form" :aria-label="`Form: ${form.join(' ')}`">
        <span
            v-for="(r, i) in form"
            :key="i"
            class="fp"
            :class="r.toLowerCase()"
            :style="{
                width: `${size}px`,
                height: `${size}px`,
                fontSize: `${size * 0.55}px`,
            }"
            :title="titleFor(r)"
        >
            {{ r }}
        </span>
    </span>
</template>
