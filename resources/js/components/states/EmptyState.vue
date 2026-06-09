<script setup>
import CornerFlag from '@/components/states/CornerFlag.vue';
import StateBlock from '@/components/states/StateBlock.vue';

defineProps({
    title: { type: String, default: 'Nothing here yet' },
    text: { type: String, default: '' },
});
</script>

<template>
    <StateBlock :title="title" :text="text" tone="neutral" art>
        <template #icon><CornerFlag /></template>
        <template #action><slot /></template>
    </StateBlock>
</template>
