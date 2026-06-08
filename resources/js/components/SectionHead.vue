<script setup>
defineProps({
    competition: { type: Object, default: null },
    title: { type: String, default: '' },
    count: { type: Number, default: null },
});
</script>

<template>
    <div class="pp-section-head">
        <span class="sh-title">
            <span
                v-if="competition"
                class="sh-comp-dot"
                :style="{ background: competition.color }"
            />
            {{ competition ? competition.name : title }}
        </span>
        <span v-if="count != null" class="sh-count"
            >{{ count }} {{ count === 1 ? 'match' : 'matches' }}</span
        >
        <span class="sh-line" />
        <slot />
    </div>
</template>
