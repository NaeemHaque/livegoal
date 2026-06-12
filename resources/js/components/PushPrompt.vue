<script setup>
import { useStorage } from '@vueuse/core';
import { computed, ref, watch } from 'vue';

import { IcBell, IcClose } from '@/components/icons';
import { usePush } from '@/composables/usePush';
import { useFavoritesStore } from '@/stores/favorites';
import { useSettingsStore } from '@/stores/settings';

/**
 * A small in-app pre-prompt offering match alerts, shown only after the user
 * follows something this session (never on page load — the native permission
 * prompt fires exclusively from the Enable button's gesture).
 */
const favorites = useFavoritesStore();
const settings = useSettingsStore();
const { supported, permission, enable } = usePush();

const dismissed = useStorage('pp_push_prompt_dismissed', false);
const followedThisSession = ref(false);
const busy = ref(false);

watch(
    () => favorites.items.length,
    (count, before) => {
        if (count > before) {
            followedThisSession.value = true;
        }
    },
);

const visible = computed(
    () =>
        supported &&
        followedThisSession.value &&
        !dismissed.value &&
        !settings.pushEnabled &&
        permission.value === 'default',
);

const accept = async () => {
    busy.value = true;

    try {
        await enable();
    } finally {
        busy.value = false;
        // Whatever the answer, never nag again unprompted.
        dismissed.value = true;
    }
};

const reject = () => {
    dismissed.value = true;
};
</script>

<template>
    <Transition name="pp-fade">
        <div v-if="visible" class="pp-pushprompt" role="status">
            <IcBell :size="16" />
            <div class="pq-text">
                <b>Goal alerts for the teams you follow?</b>
                <span
                    >Pushed even when this tab is closed. Free, no
                    account.</span
                >
            </div>
            <button
                class="pp-btn primary"
                type="button"
                :disabled="busy"
                @click="accept"
            >
                Enable alerts
            </button>
            <button
                class="pq-dismiss"
                type="button"
                aria-label="Dismiss"
                @click="reject"
            >
                <IcClose :size="14" />
            </button>
        </div>
    </Transition>
</template>
