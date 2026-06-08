<script setup>
import { IcInbox } from '@/components/icons';
import StateBlock from '@/components/states/StateBlock.vue';

defineProps({
    title: { type: String, default: 'Nothing here yet' },
    text: { type: String, default: '' },
});
</script>

<template>
    <StateBlock :title="title" :text="text" tone="neutral">
        <template #icon><IcInbox :size="28" /></template>
        <template #action><slot /></template>
    </StateBlock>
</template>
