<script setup>
// "Corner flag" empty-state art — a lone accent flag waving on a quiet pitch
// corner. The neutral, universal "nothing here" football motif used across
// every empty state. Theme-aware (pole/arc derive from text tokens) and
// reduced-motion safe (the flag stops waving).
</script>

<template>
    <div class="cf" aria-hidden="true">
        <span class="cf-arc" />
        <span class="cf-pole" />
        <span class="cf-flag">
            <svg width="50" height="30" viewBox="0 0 50 30">
                <path d="M0 0 L50 8 L0 19 Z" fill="var(--accent)" />
            </svg>
        </span>
    </div>
</template>

<style scoped>
.cf {
    position: relative;
    width: 120px;
    height: 100px;
}
.cf-pole {
    position: absolute;
    left: 40px;
    bottom: 8px;
    width: 4px;
    height: 78px;
    border-radius: 3px;
    background: linear-gradient(var(--text-2), var(--text-faint));
}
.cf-arc {
    position: absolute;
    left: 14px;
    bottom: 6px;
    width: 34px;
    height: 34px;
    border: 1.5px solid color-mix(in srgb, var(--text) 16%, transparent);
    border-left: none;
    border-bottom: none;
    border-radius: 0 100% 0 0;
}
.cf-flag {
    position: absolute;
    left: 44px;
    bottom: 78px;
    transform-origin: left center;
    animation: cf-wave 1.8s var(--ease-in-out) infinite;
}
@keyframes cf-wave {
    0%,
    100% {
        transform: rotate(0) scaleX(1);
    }
    50% {
        transform: rotate(-3deg) scaleX(0.92);
    }
}
@media (prefers-reduced-motion: reduce) {
    .cf-flag {
        animation: none;
    }
}
</style>
