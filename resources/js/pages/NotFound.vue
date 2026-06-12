<script setup>
import { RouterLink } from 'vue-router';

import { IcSearch } from '@/components/icons';

// Open the global search command palette via the same "/" shortcut AppShell binds.
function openSearch() {
    window.dispatchEvent(new KeyboardEvent('keydown', { key: '/' }));
}
</script>

<template>
    <main class="nf">
        <!-- 4 · [flipping red card] · 4 — the middle "0" is a red card -->
        <div class="nf-mark" role="img" aria-label="404">
            <span class="nf-digit display">4</span>
            <span class="nf-card" aria-hidden="true" />
            <span class="nf-digit display">4</span>
        </div>

        <h1 class="nf-title display">Page not found</h1>
        <p class="nf-hint">
            This page isn't on the team sheet. It may have been moved or the
            match has ended.
        </p>
        <div class="nf-actions">
            <RouterLink class="pp-btn primary" to="/">Back to Live</RouterLink>
            <button class="pp-btn ghost" type="button" @click="openSearch">
                <IcSearch :size="16" /> Search
            </button>
        </div>
    </main>
</template>

<style scoped>
.nf {
    min-height: 76vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 40px 24px 64px;
    background: radial-gradient(
        120% 70% at 50% 20%,
        color-mix(in srgb, var(--loss) 9%, transparent),
        transparent 55%
    );
}

.nf-mark {
    display: flex;
    align-items: center;
    gap: 16px;
    perspective: 800px;
}
.nf-digit {
    font-size: 116px;
    font-weight: 800;
    line-height: 1;
    letter-spacing: -0.02em;
    color: var(--text);
}
.nf-card {
    width: 84px;
    height: 116px;
    border-radius: 13px;
    background: linear-gradient(150deg, #ff5a5f, #d32430);
    box-shadow:
        0 16px 36px -10px rgba(244, 67, 74, 0.6),
        inset 0 0 0 1px rgba(255, 255, 255, 0.14);
    transform-style: preserve-3d;
    animation: nf-flip 3s var(--ease-in-out) infinite;
}
@keyframes nf-flip {
    0%,
    30% {
        transform: rotateY(0deg);
    }
    50%,
    80% {
        transform: rotateY(180deg);
    }
    100% {
        transform: rotateY(360deg);
    }
}

.nf-title {
    margin: 26px 0 0;
    font-size: 30px;
    font-weight: 800;
    letter-spacing: -0.01em;
    color: var(--text);
}
.nf-hint {
    margin: 8px 0 0;
    max-width: 380px;
    font-size: 14px;
    line-height: 1.55;
    color: var(--text-muted);
}
.nf-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px;
    margin-top: 24px;
}

@media (prefers-reduced-motion: reduce) {
    .nf-card {
        animation: none;
    }
}
@media (max-width: 480px) {
    .nf-digit {
        font-size: 88px;
    }
    .nf-card {
        width: 64px;
        height: 88px;
    }
}
</style>
