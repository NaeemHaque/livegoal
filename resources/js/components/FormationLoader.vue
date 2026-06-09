<script setup>
// In-app "Formation build" loader — the content-area sibling of the boot
// loader. 11 players (numbered dots) pop into a 4-3-3 on a mini pitch, hold,
// then reset. Used in place of per-card skeletons so a route change or data
// fetch shows one stable loader instead of shifting placeholders.
defineProps({
    label: { type: String, default: 'Loading' },
    minHeight: { type: String, default: '56vh' },
});

// 4-3-3 — [jersey number, x%, y%]. The staggered delay walks the lineup from
// the keeper up to the front three.
const PLAYERS = [
    [1, 50, 88],
    [2, 16, 70],
    [5, 38, 66],
    [15, 62, 66],
    [3, 84, 70],
    [8, 28, 46],
    [6, 50, 42],
    [14, 72, 46],
    [11, 22, 22],
    [9, 50, 17],
    [7, 78, 22],
];
</script>

<template>
    <div class="fl" :style="{ minHeight }">
        <div class="fl-pitch">
            <span class="fl-box top" /><span class="fl-box bot" />
            <span class="fl-half" /><span class="fl-circle" /><span
                class="fl-spot"
            />
            <span
                v-for="(p, i) in PLAYERS"
                :key="p[0]"
                class="fl-dot"
                :data-n="p[0]"
                :style="{
                    left: `${p[1]}%`,
                    top: `${p[2]}%`,
                    animationDelay: `${i * 0.12}s`,
                }"
            />
        </div>
        <div class="fl-foot">
            <div class="fl-bar" />
            <div class="fl-status">{{ label }}</div>
        </div>
    </div>
</template>

<style scoped>
.fl {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 22px;
    width: 100%;
}
.fl-pitch {
    position: relative;
    width: 236px;
    height: 304px;
    border: 1.5px solid color-mix(in srgb, var(--text) 12%, transparent);
    border-radius: 10px;
    background: repeating-linear-gradient(
        0deg,
        color-mix(in srgb, var(--pitch) 6%, transparent) 0 25px,
        color-mix(in srgb, var(--pitch) 2%, transparent) 25px 50px
    );
    overflow: hidden;
}
.fl-half {
    position: absolute;
    left: 0;
    right: 0;
    top: 50%;
    height: 1.5px;
    background: color-mix(in srgb, var(--text) 10%, transparent);
}
.fl-circle {
    position: absolute;
    left: 50%;
    top: 50%;
    width: 82px;
    height: 82px;
    transform: translate(-50%, -50%);
    border: 1.5px solid color-mix(in srgb, var(--text) 10%, transparent);
    border-radius: 50%;
}
.fl-spot {
    position: absolute;
    left: 50%;
    top: 50%;
    width: 5px;
    height: 5px;
    transform: translate(-50%, -50%);
    background: color-mix(in srgb, var(--text) 18%, transparent);
    border-radius: 50%;
}
.fl-box {
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    width: 108px;
    height: 42px;
    border: 1.5px solid color-mix(in srgb, var(--text) 10%, transparent);
}
.fl-box.top {
    top: -1.5px;
    border-top: none;
    border-radius: 0 0 6px 6px;
}
.fl-box.bot {
    bottom: -1.5px;
    border-bottom: none;
    border-radius: 6px 6px 0 0;
}
.fl-dot {
    position: absolute;
    width: 17px;
    height: 17px;
    border-radius: 50%;
    background: var(--accent);
    transform: translate(-50%, -50%) scale(0.2);
    box-shadow:
        0 0 12px -2px color-mix(in srgb, var(--accent) 60%, transparent),
        inset 0 0 0 2px rgba(10, 13, 18, 0.18);
    opacity: 0;
    animation: fl-pop 2.8s ease-in-out infinite;
}
.fl-dot::after {
    content: attr(data-n);
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    font-size: 9px;
    font-weight: 800;
    color: var(--accent-text);
    font-family: var(--font-display);
}
@keyframes fl-pop {
    0%,
    6% {
        opacity: 0;
        transform: translate(-50%, -50%) scale(0.2);
    }
    16% {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1.18);
    }
    22% {
        transform: translate(-50%, -50%) scale(1);
    }
    78% {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1);
    }
    90%,
    100% {
        opacity: 0;
        transform: translate(-50%, -50%) scale(0.2);
    }
}
.fl-foot {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
}
.fl-bar {
    width: 164px;
    height: 5px;
    border-radius: 999px;
    background: var(--surface-2);
    overflow: hidden;
    position: relative;
}
.fl-bar::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    width: 42%;
    border-radius: 999px;
    background: linear-gradient(90deg, transparent, var(--accent), var(--cyan));
    animation: fl-bar 1.4s var(--ease-out) infinite;
}
@keyframes fl-bar {
    0% {
        left: -42%;
    }
    100% {
        left: 100%;
    }
}
.fl-status {
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--text-muted);
    font-family: var(--font-display);
    animation: fl-txt 1.6s ease-in-out infinite;
}
@keyframes fl-txt {
    0%,
    100% {
        opacity: 0.5;
    }
    50% {
        opacity: 1;
    }
}
@media (prefers-reduced-motion: reduce) {
    .fl-dot {
        animation: none;
        opacity: 1;
        transform: translate(-50%, -50%) scale(1);
    }
    .fl-bar::before {
        animation: none;
        left: 0;
        width: 66%;
    }
    .fl-status {
        animation: none;
        opacity: 0.85;
    }
}
</style>
