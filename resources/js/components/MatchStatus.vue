<script setup>
import { computed } from 'vue';

import { IcAlert, IcBall, IcCheck, IcClock } from '@/components/icons';
import { useTimeFormat } from '@/composables/useTimeFormat';

const props = defineProps({
    match: { type: Object, required: true },
    small: { type: Boolean, default: false },
    // Show the kickoff date alongside the time (for cross-day upcoming lists).
    showDate: { type: Boolean, default: false },
});

const time = useTimeFormat();
const status = computed(() => props.match.status);
// Prefer ESPN's display clock ("45'+2'") when present (set on the match-detail
// view); otherwise the bare minute, or just "LIVE" until we have one.
const minuteLabel = computed(
    () =>
        props.match.displayClock ||
        (props.match.minute != null ? `${props.match.minute}'` : 'LIVE'),
);
const kickoffLabel = computed(() =>
    props.showDate
        ? time.dateTime(props.match.kickoff)
        : time.time(props.match.kickoff),
);
</script>

<template>
    <span
        v-if="status === 'LIVE' || status === 'ET'"
        class="pp-status live"
        :class="{ sm: small }"
    >
        <span
            class="dot"
            style="animation: pp-pulse 1s infinite"
            aria-hidden="true"
        />
        <span class="mono">{{ minuteLabel }}</span>
    </span>
    <span
        v-else-if="status === 'HT'"
        class="pp-status ht"
        :class="{ sm: small }"
    >
        <IcClock :size="small ? 11 : 13" /> Half-time
    </span>
    <span
        v-else-if="status === 'PEN'"
        class="pp-status pen"
        :class="{ sm: small }"
    >
        <IcBall :size="small ? 11 : 13" /> Penalties
    </span>
    <span
        v-else-if="status === 'FT'"
        class="pp-status ft"
        :class="{ sm: small }"
    >
        <IcCheck :size="small ? 11 : 13" /> Full-time
    </span>
    <span
        v-else-if="status === 'POSTPONED'"
        class="pp-status pp"
        :class="{ sm: small }"
    >
        <IcAlert :size="small ? 11 : 13" /> Postponed
    </span>
    <span v-else class="pp-status sched" :class="{ sm: small }">
        <IcClock :size="small ? 11 : 13" /> {{ kickoffLabel }}
    </span>
</template>
