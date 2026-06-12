<script setup>
// Compact inline loader — the section-level sibling of FormationLoader. Used
// where a single panel or tab inside an already-visible page is fetching (a
// detail tab, the Upcoming list) and the full-page formation loader would be
// too heavy. Three accent dots pop in sequence, echoing the formation dots at
// small scale, with an optional uppercase status label.
defineProps({
    label: { type: String, default: 'Loading' },
    minHeight: { type: Number, default: 160 },
});
</script>

<template>
    <div
        class="il"
        :style="{ minHeight: `${minHeight}px` }"
        role="status"
        aria-live="polite"
    >
        <div class="il-dots" aria-hidden="true"><span /><span /><span /></div>
        <span v-if="label" class="il-label">{{ label }}</span>
    </div>
</template>

<style scoped>
.il {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 14px;
    width: 100%;
}
.il-dots {
    display: flex;
    align-items: center;
    gap: 9px;
}
.il-dots span {
    width: 11px;
    height: 11px;
    border-radius: 50%;
    background: var(--accent);
    box-shadow: 0 0 12px -2px color-mix(in srgb, var(--accent) 55%, transparent);
    transform: scale(0.55);
    opacity: 0.4;
    animation: il-pop 1.1s var(--ease-in-out) infinite;
}
.il-dots span:nth-child(2) {
    animation-delay: 0.16s;
}
.il-dots span:nth-child(3) {
    animation-delay: 0.32s;
}
@keyframes il-pop {
    0%,
    70%,
    100% {
        transform: scale(0.55);
        opacity: 0.4;
    }
    35% {
        transform: scale(1);
        opacity: 1;
    }
}
.il-label {
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--text-muted);
    font-family: var(--font-display);
    animation: il-txt 1.6s ease-in-out infinite;
}
@keyframes il-txt {
    0%,
    100% {
        opacity: 0.5;
    }
    50% {
        opacity: 1;
    }
}
@media (prefers-reduced-motion: reduce) {
    .il-dots span {
        animation: none;
        opacity: 0.85;
        transform: scale(0.85);
    }
    .il-label {
        animation: none;
        opacity: 0.85;
    }
}
</style>
