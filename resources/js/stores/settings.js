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
    const pushEnabled = useStorage('pp_push', false); // match alerts (web push)

    // Default the clock convention to the browser locale's so nothing changes
    // for existing users until they pick one explicitly. '12h' or '24h'.
    const localeHour12 = (() => {
        try {
            return (
                new Intl.DateTimeFormat(undefined, {
                    hour: 'numeric',
                }).resolvedOptions().hour12 ?? false
            );
        } catch {
            return false;
        }
    })();
    const timeFormat = useStorage(
        'pp_time_format',
        localeHour12 ? '12h' : '24h',
    );

    const applyTheme = () => {
        document.documentElement.setAttribute('data-theme', theme.value);
    };

    // Mirror the explicit "reduced motion" preference onto <html> so the global
    // CSS guard can damp animations even when the OS setting says otherwise.
    const applyReduceMotion = () => {
        document.documentElement.toggleAttribute(
            'data-reduce-motion',
            reduceMotion.value,
        );
    };

    const toggleTheme = () => {
        theme.value = theme.value === 'dark' ? 'light' : 'dark';
    };

    watch(theme, applyTheme, { immediate: true });
    watch(reduceMotion, applyReduceMotion, { immediate: true });

    return {
        theme,
        timezone,
        refresh,
        paused,
        reduceMotion,
        pushEnabled,
        timeFormat,
        toggleTheme,
    };
});
