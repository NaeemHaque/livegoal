<script setup>
import { onClickOutside } from '@vueuse/core';
import { computed, ref } from 'vue';

import CalendarPopover from '@/components/CalendarPopover.vue';
import { IcCalendar, IcChevL, IcChevR } from '@/components/icons';
import { addDays, parseIso, today } from '@/lib/dates';

const props = defineProps({
    modelValue: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue']);

const DOW = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
const t = today();

const calOpen = ref(false);
const calWrap = ref(null);
onClickOutside(calWrap, () => (calOpen.value = false));

// A 7-day strip centered on the selected date.
const strip = computed(() =>
    Array.from({ length: 7 }, (_, i) => {
        const iso = addDays(props.modelValue, i - 3);
        const d = parseIso(iso);

        return {
            iso,
            dow: iso === t ? 'TODAY' : DOW[d.getDay()],
            label: `${d.getDate()} ${d.toLocaleString(undefined, { month: 'short' })}`,
        };
    }),
);

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
        <div class="dn-days">
            <button
                v-for="d in strip"
                :key="d.iso"
                class="dn-day"
                :class="{ on: d.iso === modelValue }"
                type="button"
                @click="select(d.iso)"
            >
                <span class="dn-dow">{{ d.dow }}</span>
                <span class="dn-date display">{{ d.label }}</span>
            </button>
        </div>
        <button
            class="dn-arrow"
            type="button"
            aria-label="Next day"
            @click="select(addDays(modelValue, 1))"
        >
            <IcChevR :size="18" />
        </button>
        <div ref="calWrap" class="dn-cal-wrap">
            <button
                class="dn-cal"
                :class="{ on: calOpen }"
                type="button"
                aria-label="Open calendar"
                :aria-expanded="calOpen"
                @click="calOpen = !calOpen"
            >
                <IcCalendar :size="18" />
            </button>
            <CalendarPopover
                v-if="calOpen"
                :selected="modelValue"
                @pick="pick"
            />
        </div>
    </div>
</template>
