<script setup>
defineProps({
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <span class="lg-logo">
        <svg
            class="lg-ball"
            width="30"
            height="30"
            viewBox="0 0 48 48"
            fill="none"
            aria-hidden="true"
        >
            <circle class="lg-body" cx="22" cy="24" r="15" />
            <polygon
                class="lg-panel"
                points="22,16 29,21 26.5,29.5 17.5,29.5 15,21"
            />
            <g
                class="lg-seam"
                stroke-width="1.5"
                fill="none"
                stroke-linecap="round"
            >
                <path
                    d="M22 16V9M29 21l6.3-2.3M26.5 29.5l4.4 5.5M17.5 29.5l-4.4 5.5M15 21l-6.3-2.3"
                />
            </g>
            <circle
                class="lg-seam"
                cx="22"
                cy="24"
                r="15"
                fill="none"
                stroke-width="1.6"
            />
            <circle cx="39" cy="11" r="5" fill="#FF3D3D" />
        </svg>
        <span v-if="!compact" class="lg-word"
            >Live<span class="accent">Goal</span></span
        >
    </span>
</template>

<style scoped>
/* The football body/panel/seams follow the theme tokens so the mark reads on
   both the dark and light header (white ball on dark, dark ball on light). */
.lg-logo {
    display: inline-flex;
    align-items: center;
    gap: 10px;
}
.lg-ball {
    overflow: visible;
    flex: none;
}
.lg-body {
    fill: var(--text);
}
.lg-panel {
    fill: var(--bg-base);
}
.lg-seam {
    stroke: var(--bg-base);
}
.lg-word {
    font-family: var(--font-display);
    font-weight: 800;
    font-size: 23px;
    letter-spacing: -0.02em;
    line-height: 1;
    color: var(--text);
}
.lg-word .accent {
    color: var(--accent);
}
</style>
