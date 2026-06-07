<script setup>
import AppShell from '@/components/AppShell.vue';
import { useSettingsStore } from '@/stores/settings';

useSettingsStore();
</script>

<template>
    <AppShell />
</template>
