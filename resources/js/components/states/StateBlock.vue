<script setup>
defineProps({
    title: { type: String, required: true },
    text: { type: String, default: '' },
    tone: { type: String, default: 'neutral' }, // neutral | error | offline
});
</script>

<template>
    <div class="pp-state" :class="tone">
        <div class="ic"><slot name="icon" /></div>
        <div class="st-title display">{{ title }}</div>
        <div v-if="text" class="st-text">{{ text }}</div>
        <slot name="action" />
    </div>
</template>

<style scoped>
.pp-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 10px;
    padding: 56px 24px;
    color: var(--text-muted);
}

.pp-state .ic {
    width: 64px;
    height: 64px;
    display: grid;
    place-items: center;
    border-radius: 18px;
    background: var(--surface-2);
    border: 1px solid var(--border);
    color: var(--text-2);
    margin-bottom: 4px;
}

.pp-state.error .ic {
    color: var(--loss);
    border-color: color-mix(in srgb, var(--loss) 40%, var(--border));
    background: color-mix(in srgb, var(--loss) 8%, var(--surface-2));
}

.pp-state.offline .ic {
    color: var(--draw);
}

.pp-state .st-title {
    font-size: 22px;
    font-weight: 700;
    color: var(--text);
    letter-spacing: -0.01em;
}

.pp-state .st-text {
    font-size: 14px;
    max-width: 360px;
    line-height: 1.55;
}
</style>
