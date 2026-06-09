<script setup>
import { IcBall } from '@/components/icons';

defineProps({
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <span class="pp-logo">
        <span class="mark"><IcBall :size="20" /></span>
        <span v-if="!compact" class="word">
            <b>Live<span class="accent">Goal</span></b>
            <small>Live Football</small>
        </span>
    </span>
</template>
