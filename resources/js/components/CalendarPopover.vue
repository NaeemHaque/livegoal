<script setup>
import { computed, ref } from 'vue';

import { IcCalendar, IcChevL, IcChevR } from '@/components/icons';
import { isoDate, parseIso, today } from '@/lib/dates';

const props = defineProps({
    selected: { type: String, required: true },
});

const emit = defineEmits(['pick']);

const MONTHS = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];
const DOW = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

const sel = parseIso(props.selected);
const view = ref({ y: sel.getFullYear(), m: sel.getMonth() });
const t = today();

const cells = computed(() => {
    const { y, m } = view.value;
    const startPad = (new Date(y, m, 1).getDay() + 6) % 7; // Monday-first
    const daysInMonth = new Date(y, m + 1, 0).getDate();
    const out = [];

    for (let i = 0; i < startPad; i++) {
        out.push(null);
    }

    for (let d = 1; d <= daysInMonth; d++) {
        out.push(new Date(y, m, d));
    }

    return out;
});

function stepMonth(delta) {
    const nm = view.value.m + delta;
    view.value = {
        y: view.value.y + Math.floor(nm / 12),
        m: ((nm % 12) + 12) % 12,
    };
}
</script>

<template>
    <div class="pp-cal" role="dialog" aria-label="Choose date">
        <div class="cal-head">
            <button
                class="cal-nav"
                type="button"
                aria-label="Previous month"
                @click="stepMonth(-1)"
            >
                <IcChevL :size="16" />
            </button>
            <span class="cal-title display"
                >{{ MONTHS[view.m] }} {{ view.y }}</span
            >
            <button
                class="cal-nav"
                type="button"
                aria-label="Next month"
                @click="stepMonth(1)"
            >
                <IcChevR :size="16" />
            </button>
        </div>
        <div class="cal-dow">
            <span v-for="d in DOW" :key="d">{{ d }}</span>
        </div>
        <div class="cal-grid">
            <template v-for="(d, i) in cells" :key="i">
                <span v-if="!d" class="cal-cell empty" />
                <button
                    v-else
                    class="cal-cell sel-able"
                    :class="{
                        on: isoDate(d) === selected,
                        today: isoDate(d) === t,
                    }"
                    type="button"
                    @click="emit('pick', isoDate(d))"
                >
                    {{ d.getDate() }}
                </button>
            </template>
        </div>
        <div class="cal-foot">
            <button
                class="cal-today-btn"
                type="button"
                @click="emit('pick', t)"
            >
                <IcCalendar :size="13" /> Jump to Today
            </button>
        </div>
    </div>
</template>
