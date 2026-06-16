<script setup>
import { ref } from 'vue';

import { IcChevD } from '@/components/icons';

/**
 * A section with a clickable header (competition or plain title + count) that
 * collapses its body. Used to tame long, grouped match lists — collapsed
 * sections shrink to a single tappable header showing the match count.
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
            <span v-if="count != null" class="sh-count">{{ count }}</span>
            <span class="sh-line" />
            <IcChevD
                class="pp-collapse-chev"
                :class="{ collapsed: !open }"
                :size="18"
            />
        </button>
        <div v-show="open" class="pp-collapse-body">
            <slot />
        </div>
    </div>
</template>
