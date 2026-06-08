<script setup>
import { IcAlert } from '@/components/icons';
import StateBlock from '@/components/states/StateBlock.vue';

defineProps({
    title: { type: String, default: 'Something went wrong' },
    text: { type: String, default: "Couldn't load this right now." },
});

const emit = defineEmits(['retry']);
</script>

<template>
    <StateBlock :title="title" :text="text" tone="error">
        <template #icon><IcAlert :size="28" /></template>
        <template #action>
            <button
                class="pp-btn ghost sm"
                type="button"
                @click="emit('retry')"
            >
                Try again
            </button>
        </template>
    </StateBlock>
</template>
