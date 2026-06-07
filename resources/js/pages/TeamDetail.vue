<script setup>
import PagePlaceholder from '@/components/PagePlaceholder.vue';

defineProps({ id: { type: String, required: true } });
</script>

<template>
    <PagePlaceholder
        title="Team"
        :subtitle="`Team #${id} — full screen in Phase 5.`"
    />
</template>
