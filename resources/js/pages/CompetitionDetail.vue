<script setup>
import PagePlaceholder from '@/components/PagePlaceholder.vue';

defineProps({ id: { type: String, required: true } });
</script>

<template>
    <PagePlaceholder
        title="Competition"
        :subtitle="`Competition #${id} — full screen in Phase 5.`"
    />
</template>
