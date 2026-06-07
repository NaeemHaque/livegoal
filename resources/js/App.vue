<script setup>
import { RouterView } from 'vue-router';

import { useSettingsStore } from '@/stores/settings';

useSettingsStore();
</script>

<template>
    <RouterView />
</template>
