<script setup>
import { onClickOutside } from '@vueuse/core';
import { computed, ref } from 'vue';

import CalendarPopover from '@/components/CalendarPopover.vue';
import { IcChevD, IcChevL, IcChevR } from '@/components/icons';
import { addDays, parseIso, today } from '@/lib/dates';

const props = defineProps({
    modelValue: { type: String, required: true },
    liveCount: { type: Number, default: 0 },
});

const emit = defineEmits(['update:modelValue']);

const t = today();

const calOpen = ref(false);
const calWrap = ref(null);
onClickOutside(calWrap, () => (calOpen.value = false));

const label = computed(() => {
    const d = parseIso(props.modelValue);
    const date = `${d.getDate()} ${d.toLocaleString(undefined, { month: 'short' })}`;
    const prefix =
        props.modelValue === t
            ? 'Today'
            : d.toLocaleString(undefined, { weekday: 'short' });

    return `${prefix}, ${date}`;
});

const select = (iso) => emit('update:modelValue', iso);
const pick = (iso) => {
    select(iso);
    calOpen.value = false;
};
</script>

<template>
    <div class="pp-datenav">
        <button
            class="dn-arrow"
            type="button"
            aria-label="Previous day"
            @click="select(addDays(modelValue, -1))"
        >
            <IcChevL :size="18" />
        </button>

        <div ref="calWrap" class="dn-cal-wrap">
            <button
                class="dn-current"
                :class="{ on: calOpen }"
                type="button"
                aria-label="Pick a date"
                :aria-expanded="calOpen"
                @click="calOpen = !calOpen"
            >
                <span
                    v-if="liveCount > 0"
                    class="dn-current-live"
                    aria-hidden="true"
                />
                <span class="dn-current-label">{{ label }}</span>
                <IcChevD :size="14" />
            </button>
            <CalendarPopover
                v-if="calOpen"
                :selected="modelValue"
                @pick="pick"
            />
        </div>

        <button
            class="dn-arrow"
            type="button"
            aria-label="Next day"
            @click="select(addDays(modelValue, 1))"
        >
            <IcChevR :size="18" />
        </button>
    </div>
</template>
