<script setup>
import PagePlaceholder from '@/components/PagePlaceholder.vue';

defineProps({ id: { type: String, required: true } });
</script>

<template>
    <PagePlaceholder
        title="Player"
        :subtitle="`Player #${id} — full screen in Phase 6.`"
    />
</template>
