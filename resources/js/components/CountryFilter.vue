<script setup>
import { onClickOutside } from '@vueuse/core';
import { computed, ref } from 'vue';

import Crest from '@/components/Crest.vue';
import { IcCheck, IcChevD, IcClose, IcSearch } from '@/components/icons';

const props = defineProps({
    // [{ name, crest? }] — the countries/teams present in the current list.
    options: { type: Array, default: () => [] },
    modelValue: { type: Array, default: () => [] }, // selected names
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const wrap = ref(null);
const query = ref('');
onClickOutside(wrap, () => (open.value = false));

const selected = computed(() => new Set(props.modelValue));

const label = computed(() => {
    if (props.modelValue.length === 0) {
        return 'All countries';
    }

    if (props.modelValue.length === 1) {
        return props.modelValue[0];
    }

    return `${props.modelValue.length} countries`;
});

const filteredOptions = computed(() => {
    const q = query.value.trim().toLowerCase();

    if (!q) {
        return props.options;
    }

    return props.options.filter((o) => o.name.toLowerCase().includes(q));
});

const toggle = (name) => {
    const next = new Set(selected.value);

    if (next.has(name)) {
        next.delete(name);
    } else {
        next.add(name);
    }

    emit('update:modelValue', [...next]);
};

const clear = () => emit('update:modelValue', []);
</script>

<template>
    <div ref="wrap" class="pp-countryfilter">
        <button
            class="cf-btn"
            :class="{ on: open || modelValue.length > 0 }"
            type="button"
            aria-label="Filter by country"
            :aria-expanded="open"
            @click="open = !open"
        >
            <span class="cf-label">{{ label }}</span>
            <span v-if="modelValue.length > 1" class="cf-count">{{
                modelValue.length
            }}</span>
            <IcChevD :size="14" />
        </button>

        <div v-if="open" class="cf-pop">
            <div class="cf-search">
                <IcSearch :size="14" />
                <input
                    v-model="query"
                    type="search"
                    placeholder="Search countries…"
                    aria-label="Search countries"
                />
                <button
                    v-if="modelValue.length"
                    class="cf-clear"
                    type="button"
                    @click="clear"
                >
                    <IcClose :size="13" /> Clear
                </button>
            </div>
            <div class="cf-list" role="listbox" aria-multiselectable="true">
                <button
                    v-for="o in filteredOptions"
                    :key="o.name"
                    class="cf-item"
                    :class="{ on: selected.has(o.name) }"
                    type="button"
                    role="option"
                    :aria-selected="selected.has(o.name)"
                    @click="toggle(o.name)"
                >
                    <Crest :team="o" :size="18" />
                    <span class="cf-name">{{ o.name }}</span>
                    <IcCheck v-if="selected.has(o.name)" :size="14" />
                </button>
                <div v-if="filteredOptions.length === 0" class="cf-empty">
                    No countries match “{{ query }}”
                </div>
            </div>
        </div>
    </div>
</template>
