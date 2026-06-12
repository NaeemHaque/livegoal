<script setup>
import Crest from '@/components/Crest.vue';

defineProps({
    team: { type: Object, default: null },
    size: { type: Number, default: 24 },
    code: { type: Boolean, default: false }, // show short code vs full name
    reverse: { type: Boolean, default: false },
    strong: { type: Boolean, default: false },
});
</script>

<template>
    <span class="pp-teamchip" :class="{ rev: reverse }">
        <Crest :team="team" :size="size" />
        <span class="name" :class="{ strong }">{{
            code ? team?.short : team?.name
        }}</span>
    </span>
</template>
