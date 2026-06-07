<script setup>
import { RouterLink } from 'vue-router';
</script>

<template>
    <main class="notfound">
        <p class="code mono">404</p>
        <h1>Page not found</h1>
        <p class="hint">The page you're looking for doesn't exist.</p>
        <RouterLink class="back" to="/">Back to Live</RouterLink>
    </main>
</template>

<style scoped>
.notfound {
    min-height: 100vh;
    display: grid;
    place-content: center;
    gap: var(--s-3);
    text-align: center;
}

.code {
    margin: 0;
    font-size: var(--t-display-lg);
    color: var(--accent);
}

h1 {
    margin: 0;
    font-size: var(--t-h1);
}

.hint {
    margin: 0;
    color: var(--text-muted);
}

.back {
    justify-self: center;
    margin-top: var(--s-2);
    padding: var(--s-2) var(--s-4);
    border-radius: var(--r-pill);
    background: var(--accent);
    color: var(--accent-text);
    font-weight: 600;
}
</style>
