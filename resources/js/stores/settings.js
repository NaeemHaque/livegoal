import { usePreferredDark, useStorage } from '@vueuse/core';
import { defineStore } from 'pinia';
import { watch } from 'vue';

export const useSettingsStore = defineStore('settings', () => {
    const prefersDark = usePreferredDark();
    const theme = useStorage('pp_theme', prefersDark.value ? 'dark' : 'light');

    const applyTheme = () => {
        document.documentElement.setAttribute('data-theme', theme.value);
    };

    const toggleTheme = () => {
        theme.value = theme.value === 'dark' ? 'light' : 'dark';
    };

    watch(theme, applyTheme, { immediate: true });

    return { theme, toggleTheme };
});
