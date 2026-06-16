<script setup>
import { ref } from 'vue';

import { IcChevD } from '@/components/icons';

/**
 * A section whose header (the standard section-head line: title + match count +
 * divider) toggles its body, with a right-side collapse chevron. No box/border
 * — it reads like the page's other section headers, just collapsible.
 */
const props = defineProps({
    competition: { type: Object, default: null },
    title: { type: String, default: '' },
    count: { type: Number, default: null },
    defaultOpen: { type: Boolean, default: true },
});

const open = ref(props.defaultOpen);
</script>

<template>
    <div class="pp-section">
        <button
            type="button"
            class="pp-section-head pp-collapse-head"
            :class="{ open }"
            :aria-expanded="open"
            @click="open = !open"
        >
            <span class="sh-title">
                <slot name="icon" />
                <span
                    v-if="competition"
                    class="sh-comp-dot"
                    :style="{ background: competition.color }"
                />
                {{ competition ? competition.name : title }}
            </span>
            <span class="sh-line" />
            <span v-if="count != null" class="sh-count"
                >{{ count }} {{ count === 1 ? 'match' : 'matches' }}</span
            >
            <IcChevD
                class="pp-collapse-chev"
                :class="{ collapsed: !open }"
                :size="18"
            />
        </button>
        <div v-show="open">
            <slot />
        </div>
    </div>
</template>
