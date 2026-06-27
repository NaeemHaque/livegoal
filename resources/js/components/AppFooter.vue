<script setup>
// Server-rendered content pages (not SPA routes), so plain <a> for a full nav.
const repo = 'https://github.com/NaeemHaque/livegoal';
const year = new Date().getFullYear();
</script>

<template>
    <footer class="pp-footer">
        <span class="pf-copy">© {{ year }} LiveGoal · Open source (MIT)</span>
        <nav class="pf-links" aria-label="Footer">
            <a href="/about">About</a>
            <a href="/contact">Contact</a>
            <a href="/privacy">Privacy</a>
            <a :href="repo" target="_blank" rel="noopener noreferrer"
                >GitHub ↗</a
            >
        </nav>
    </footer>
</template>

<style scoped>
.pp-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px 24px;
    padding: 20px 22px;
    border-top: 1px solid var(--border);
    color: var(--text-muted);
    font-size: 13px;
}

.pf-links {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 22px;
}

.pf-links a {
    color: var(--text-muted);
    text-decoration: none;
    font-weight: 600;
}

.pf-links a:hover {
    color: var(--accent);
}

@media (max-width: 640px) {
    .pp-footer {
        flex-direction: column;
        justify-content: center;
        text-align: center;
        gap: 12px;
        padding: 20px 16px;
    }

    .pf-links {
        justify-content: center;
    }
}
</style>
