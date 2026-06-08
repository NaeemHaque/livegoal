<script setup>
defineProps({
    label: { type: String, default: 'LIVE' },
    small: { type: Boolean, default: false },
});
</script>

<template>
    <span class="pp-live-badge" :class="{ sm: small }" role="status">
        <span class="dot" aria-hidden="true" />
        <span class="lbl">{{ label }}</span>
    </span>
</template>
