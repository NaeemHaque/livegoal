<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    team: { type: Object, default: null },
    size: { type: Number, default: 28 },
    ring: { type: Boolean, default: false },
});

const failed = ref(false);

// Reset the broken-image fallback when the crest changes, so a recycled node
// (standings rows, squad grids, detail-page nav) doesn't stay stuck on initials.
watch(
    () => props.team?.crest,
    () => (failed.value = false),
);
const showImg = computed(() => Boolean(props.team?.crest) && !failed.value);
const mono = computed(() =>
    (props.team?.tla || props.team?.short || props.team?.name || '?')
        .slice(0, 3)
        .toUpperCase(),
);
</script>

<template>
    <span
        class="pp-crest"
        :class="[showImg ? 'flag' : 'mono', { ring }]"
        :style="{
            width: `${size}px`,
            height: `${size}px`,
            fontSize: showImg ? undefined : `${size * 0.36}px`,
            background: showImg ? undefined : 'var(--surface-3)',
            color: showImg ? undefined : 'var(--text-2)',
        }"
        :title="team?.name"
    >
        <img
            v-if="showImg"
            :src="team.crest"
            :alt="team?.name"
            loading="lazy"
            @error="failed = true"
        />
        <span v-else>{{ mono }}</span>
    </span>
</template>
