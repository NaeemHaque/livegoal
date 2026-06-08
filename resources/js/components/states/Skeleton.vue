<script setup>
defineProps({
    w: { type: [Number, String], default: '100%' },
    h: { type: [Number, String], default: 14 },
    r: { type: [Number, String], default: 8 },
});

const px = (v) => (typeof v === 'number' ? `${v}px` : v);
</script>

<template>
    <span
        class="pp-skel"
        :style="{ width: px(w), height: px(h), borderRadius: px(r) }"
        aria-hidden="true"
    />
</template>

<style scoped>
.pp-skel {
    display: inline-block;
    background: linear-gradient(
        90deg,
        var(--skeleton-base) 25%,
        var(--skeleton-shine) 50%,
        var(--skeleton-base) 75%
    );
    background-size: 480px 100%;
    animation: pp-shimmer 1.4s linear infinite;
}
</style>
