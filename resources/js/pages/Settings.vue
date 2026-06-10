<script setup>
import { IcMoon, IcSun } from '@/components/icons';
import { useSettingsStore } from '@/stores/settings';

const settings = useSettingsStore();

const zones = [
    { id: 'local', label: 'Local' },
    { id: 'UTC', label: 'UTC' },
    { id: 'Europe/London', label: 'London' },
    { id: 'America/New_York', label: 'New York' },
];
const intervals = [10, 15, 30];
</script>

<template>
    <div class="pp-page pp-rise" style="max-width: 680px; margin-inline: auto">
        <div class="pp-pagehead">
            <div>
                <h1>Settings</h1>
                <div class="ph-sub">Personalise LiveGoal</div>
            </div>
        </div>

        <div class="pp-panel" style="padding: 0; margin-bottom: 18px">
            <div class="pp-setrow">
                <div>
                    <div class="sr-label">Theme</div>
                    <div class="sr-desc">Stadium Night or Daylight</div>
                </div>
                <div class="pp-segmented" role="radiogroup" aria-label="Theme">
                    <button
                        role="radio"
                        :aria-checked="settings.theme === 'dark'"
                        :class="{ on: settings.theme === 'dark' }"
                        type="button"
                        @click="settings.theme = 'dark'"
                    >
                        <IcMoon :size="14" />Dark
                    </button>
                    <button
                        role="radio"
                        :aria-checked="settings.theme === 'light'"
                        :class="{ on: settings.theme === 'light' }"
                        type="button"
                        @click="settings.theme = 'light'"
                    >
                        <IcSun :size="14" />Light
                    </button>
                </div>
            </div>

            <div class="pp-setrow">
                <div>
                    <div class="sr-label">Time zone</div>
                    <div class="sr-desc">
                        Kick-off times are shown in this zone
                    </div>
                </div>
                <div
                    class="pp-segmented"
                    role="radiogroup"
                    aria-label="Time zone"
                >
                    <button
                        v-for="z in zones"
                        :key="z.id"
                        role="radio"
                        :aria-checked="settings.timezone === z.id"
                        :class="{ on: settings.timezone === z.id }"
                        type="button"
                        @click="settings.timezone = z.id"
                    >
                        {{ z.label }}
                    </button>
                </div>
            </div>

            <div class="pp-setrow">
                <div>
                    <div class="sr-label">Time format</div>
                    <div class="sr-desc">
                        Show kick-off times as 24-hour or AM/PM
                    </div>
                </div>
                <div
                    class="pp-segmented"
                    role="radiogroup"
                    aria-label="Time format"
                >
                    <button
                        role="radio"
                        :aria-checked="settings.timeFormat === '24h'"
                        :class="{ on: settings.timeFormat === '24h' }"
                        type="button"
                        @click="settings.timeFormat = '24h'"
                    >
                        24h
                    </button>
                    <button
                        role="radio"
                        :aria-checked="settings.timeFormat === '12h'"
                        :class="{ on: settings.timeFormat === '12h' }"
                        type="button"
                        @click="settings.timeFormat = '12h'"
                    >
                        12h
                    </button>
                </div>
            </div>

            <div class="pp-setrow">
                <div>
                    <div class="sr-label">Auto-refresh interval</div>
                    <div class="sr-desc">How often live scores update</div>
                </div>
                <div
                    class="pp-segmented"
                    role="radiogroup"
                    aria-label="Auto-refresh interval"
                >
                    <button
                        v-for="n in intervals"
                        :key="n"
                        role="radio"
                        :aria-checked="settings.refresh === n"
                        :class="{ on: settings.refresh === n }"
                        type="button"
                        @click="settings.refresh = n"
                    >
                        {{ n }}s
                    </button>
                </div>
            </div>

            <div class="pp-setrow">
                <div>
                    <div class="sr-label">Auto-refresh</div>
                    <div class="sr-desc">Pause to save data &amp; battery</div>
                </div>
                <button
                    class="pp-switch"
                    :class="{ on: !settings.paused }"
                    type="button"
                    role="switch"
                    :aria-checked="!settings.paused"
                    aria-label="Auto-refresh live scores"
                    @click="settings.paused = !settings.paused"
                />
            </div>

            <div class="pp-setrow">
                <div>
                    <div class="sr-label">Reduced motion</div>
                    <div class="sr-desc">
                        Minimise animations (also follows your OS setting)
                    </div>
                </div>
                <button
                    class="pp-switch"
                    :class="{ on: settings.reduceMotion }"
                    type="button"
                    role="switch"
                    :aria-checked="settings.reduceMotion"
                    aria-label="Reduced motion"
                    @click="settings.reduceMotion = !settings.reduceMotion"
                />
            </div>
        </div>
    </div>
</template>
