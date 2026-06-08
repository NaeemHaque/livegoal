<script setup>
import { useEventListener, useOnline } from '@vueuse/core';
import { computed } from 'vue';
import { useRouter } from 'vue-router';

import GoalToast from '@/components/GoalToast.vue';
import {
    IcBell,
    IcMoon,
    IcSearch,
    IcSettings,
    IcSun,
    IcWifiOff,
} from '@/components/icons';
import LiveTicker from '@/components/LiveTicker.vue';
import Logo from '@/components/Logo.vue';
import RefreshIndicator from '@/components/RefreshIndicator.vue';
import TabBar from '@/components/TabBar.vue';
import TopNav from '@/components/TopNav.vue';
import { useLiveMatches } from '@/composables/useLiveMatches';
import { useMatchesStore } from '@/stores/matches';
import { useSettingsStore } from '@/stores/settings';

const router = useRouter();
const settings = useSettingsStore();
const matches = useMatchesStore();
const online = useOnline();

// Start the site-wide, visibility-aware live feed.
useLiveMatches();

const goalAnnounce = computed(() => {
    const g = matches.lastGoal;

    return g ? `Goal for ${g.team?.name}. Score now ${g.scoreline}.` : '';
});

const openSearch = () => router.push('/search');

// "/" or ⌘K / Ctrl+K opens search (ignored while typing in a field).
useEventListener(window, 'keydown', (e) => {
    const tag = (e.target?.tagName || '').toLowerCase();
    const typing =
        tag === 'input' || tag === 'textarea' || e.target?.isContentEditable;

    if (
        (e.key === '/' && !typing) ||
        ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k')
    ) {
        e.preventDefault();
        openSearch();
    }
});
</script>

<template>
    <div class="pp-app">
        <div class="pp-shell">
            <header class="pp-topbar">
                <div class="pp-topbar-inner">
                    <RouterLink to="/" class="tb-logo" aria-label="SocPlay home"
                        ><Logo
                    /></RouterLink>
                    <span class="tb-spacer" />
                    <div
                        class="pp-search"
                        role="button"
                        tabindex="0"
                        aria-label="Search teams and competitions"
                        @click="openSearch"
                        @keydown.enter="openSearch"
                        @keydown.space.prevent="openSearch"
                    >
                        <IcSearch :size="16" />
                        <input
                            placeholder="Search teams, competitions…"
                            readonly
                            tabindex="-1"
                            aria-hidden="true"
                        />
                        <span class="kbd">/</span>
                    </div>
                    <RefreshIndicator
                        :seconds="settings.refresh"
                        :paused="settings.paused"
                        :last-updated="matches.lastUpdated"
                    />
                    <button
                        class="pp-iconbtn sm"
                        aria-label="Following"
                        @click="router.push('/favorites')"
                    >
                        <IcBell :size="17" />
                        <span class="badge-dot" />
                    </button>
                    <button
                        class="pp-iconbtn sm"
                        :aria-label="
                            settings.theme === 'dark'
                                ? 'Switch to light theme'
                                : 'Switch to dark theme'
                        "
                        @click="settings.toggleTheme"
                    >
                        <IcSun v-if="settings.theme === 'dark'" :size="17" />
                        <IcMoon v-else :size="17" />
                    </button>
                    <button
                        class="pp-iconbtn sm"
                        aria-label="Settings"
                        @click="router.push('/settings')"
                    >
                        <IcSettings :size="17" />
                    </button>
                </div>
            </header>

            <TopNav />

            <div class="pp-mobile-top">
                <RouterLink to="/" class="tb-logo" aria-label="SocPlay home"
                    ><Logo
                /></RouterLink>
                <span class="tb-spacer" />
                <button
                    class="pp-iconbtn"
                    aria-label="Search"
                    @click="openSearch"
                >
                    <IcSearch :size="18" />
                </button>
                <button
                    class="pp-iconbtn"
                    aria-label="Toggle theme"
                    @click="settings.toggleTheme"
                >
                    <IcSun v-if="settings.theme === 'dark'" :size="18" />
                    <IcMoon v-else :size="18" />
                </button>
            </div>

            <LiveTicker :matches="matches.live" />

            <div v-if="!online" class="pp-offline-banner" role="status">
                <IcWifiOff :size="15" /> You're offline — scores may be out of
                date.
            </div>

            <main class="pp-main">
                <div aria-live="polite" class="sr-only">{{ goalAnnounce }}</div>
                <RouterView />
            </main>
        </div>

        <TabBar />

        <GoalToast
            v-if="!settings.reduceMotion"
            :goal="matches.lastGoal"
            @done="matches.clearGoal()"
        />
    </div>
</template>

<style scoped>
.pp-offline-banner {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 600;
    color: var(--draw);
    background: color-mix(in srgb, var(--draw) 12%, var(--surface));
    border-bottom: 1px solid var(--border);
}
</style>
