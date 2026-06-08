import { usePreferredDark, useStorage } from '@vueuse/core';
import { defineStore } from 'pinia';
import { watch } from 'vue';

export const useSettingsStore = defineStore('settings', () => {
    const prefersDark = usePreferredDark();

    // All persisted to localStorage so preferences survive reloads.
    const theme = useStorage('pp_theme', prefersDark.value ? 'dark' : 'light');
    const timezone = useStorage('pp_timezone', 'local'); // 'local' or an IANA name
    const refresh = useStorage('pp_refresh', 15); // live poll interval, seconds
    const paused = useStorage('pp_paused', false); // pause live polling
    const reduceMotion = useStorage('pp_reduce_motion', false);

    const applyTheme = () => {
        document.documentElement.setAttribute('data-theme', theme.value);
    };

    const toggleTheme = () => {
        theme.value = theme.value === 'dark' ? 'light' : 'dark';
    };

    watch(theme, applyTheme, { immediate: true });

    return { theme, timezone, refresh, paused, reduceMotion, toggleTheme };
});
