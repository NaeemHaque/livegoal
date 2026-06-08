<script setup>
import { IcWifiOff } from '@/components/icons';
import StateBlock from '@/components/states/StateBlock.vue';

defineProps({
    title: { type: String, default: "You're offline" },
    text: { type: String, default: 'Reconnect to load live scores.' },
});
</script>

<template>
    <StateBlock :title="title" :text="text" tone="offline">
        <template #icon><IcWifiOff :size="28" /></template>
    </StateBlock>
</template>
