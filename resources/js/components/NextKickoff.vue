<script setup>
import { useNow } from '@vueuse/core';
import { computed } from 'vue';
import { useRouter } from 'vue-router';

import Crest from '@/components/Crest.vue';
import { useTimeFormat } from '@/composables/useTimeFormat';

const props = defineProps({
    match: { type: Object, required: true },
});

const router = useRouter();
const time = useTimeFormat();
const now = useNow({ interval: 1000 });

const remainingMs = computed(() =>
    Math.max(0, new Date(props.match.kickoff).getTime() - now.value.getTime()),
);

const boxes = computed(() => {
    const total = Math.floor(remainingMs.value / 1000);
    const pad = (n) => String(n).padStart(2, '0');

    const list = [
        { label: 'Hrs', value: pad(Math.floor((total % 86400) / 3600)) },
        { label: 'Min', value: pad(Math.floor((total % 3600) / 60)) },
        { label: 'Sec', value: pad(total % 60) },
    ];

    const days = Math.floor(total / 86400);

    if (days > 0) {
        list.unshift({ label: 'Days', value: pad(days) });
    }

    return list;
});

const phase = computed(() => {
    if (props.match.group) {
        return props.match.group.replace(/^GROUP_/, 'Group ');
    }

    const stage = props.match.stage;

    if (
        stage &&
        !['GROUP_STAGE', 'LEAGUE_STAGE', 'REGULAR_SEASON'].includes(stage)
    ) {
        return stage
            .replace(/_/g, ' ')
            .toLowerCase()
            .replace(/\b\w/g, (c) => c.toUpperCase());
    }

    return null;
});

const meta = computed(() =>
    [
        props.match.competition?.name,
        phase.value,
        props.match.venue,
        time.time(props.match.kickoff),
    ].filter(Boolean),
);
</script>

<template>
    <div class="pp-nextkick">
        <div class="nk-glow" aria-hidden="true" />

        <div class="nk-eyebrow">No matches live right now · Next kickoff</div>

        <div class="nk-teams">
            <div class="nk-team">
                <Crest :team="match.home" :size="60" />
                <span class="nk-name">{{ match.home?.name }}</span>
            </div>
            <span class="nk-vs">VS</span>
            <div class="nk-team">
                <Crest :team="match.away" :size="60" />
                <span class="nk-name">{{ match.away?.name }}</span>
            </div>
        </div>

        <div class="nk-countdown" role="timer" aria-label="Time until kickoff">
            <div v-for="b in boxes" :key="b.label" class="nk-box">
                <b class="display tnum">{{ b.value }}</b>
                <small>{{ b.label }}</small>
            </div>
        </div>

        <div class="nk-meta">{{ meta.join(' · ') }}</div>

        <div class="nk-actions">
            <button
                class="pp-btn primary"
                type="button"
                @click="router.push('/matches')"
            >
                All fixtures
            </button>
        </div>
    </div>
</template>

<style scoped>
.pp-nextkick {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 18px;
    padding: 40px 24px 36px;
    text-align: center;
    overflow: hidden;
}
.nk-glow {
    position: absolute;
    inset: 0;
    background: radial-gradient(
        60% 70% at 50% 0%,
        color-mix(in srgb, var(--accent) 11%, transparent),
        transparent 70%
    );
    pointer-events: none;
}
.nk-eyebrow {
    position: relative;
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--text-muted);
}
.nk-teams {
    position: relative;
    display: flex;
    align-items: center;
    gap: 28px;
}
.nk-team {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}
.nk-name {
    font-family: var(--font-display);
    font-size: 16px;
    font-weight: 700;
}
.nk-vs {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.06em;
    color: var(--text-muted);
}
.nk-countdown {
    position: relative;
    display: flex;
    gap: 10px;
}
.nk-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    min-width: 74px;
    padding: 12px 10px;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: var(--r-md);
}
.nk-box b {
    font-size: 30px;
    font-weight: 800;
    line-height: 1;
}
.nk-box small {
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--text-muted);
}
.nk-meta {
    position: relative;
    font-size: 13.5px;
    color: var(--text-muted);
}
.nk-actions {
    position: relative;
}
</style>
