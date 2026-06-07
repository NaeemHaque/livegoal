<script setup>
import { useSettingsStore } from '@/stores/settings';

const settings = useSettingsStore();
</script>

<template>
    <main class="placeholder">
        <h1 class="display">Soc<span>Play</span></h1>
        <p>Live Football Scores</p>
        <button class="toggle" @click="settings.toggleTheme">
            Theme: {{ settings.theme }}
        </button>
    </main>
</template>

<style scoped>
.placeholder {
    min-height: 100vh;
    display: grid;
    place-content: center;
    gap: var(--s-4);
    text-align: center;
}

h1 {
    margin: 0;
    font-size: var(--t-display-xl);
    font-weight: 800;
    letter-spacing: -0.01em;
}

h1 span {
    color: var(--accent);
}

p {
    margin: 0;
    color: var(--text-muted);
}

.toggle {
    justify-self: center;
    padding: var(--s-2) var(--s-4);
    border: 1px solid var(--border);
    border-radius: var(--r-pill);
    background: var(--surface-2);
    color: var(--text);
}
</style>
