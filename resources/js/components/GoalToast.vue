<script setup>
import { watch } from 'vue';

import Crest from '@/components/Crest.vue';
import { IcBall } from '@/components/icons';

const props = defineProps({
    goal: { type: Object, default: null },
});

const emit = defineEmits(['done']);

let timer = null;

watch(
    () => props.goal,
    (g) => {
        if (timer) {
            clearTimeout(timer);
        }

        if (g) {
            timer = setTimeout(() => emit('done'), 4200);
        }
    },
);
</script>

<template>
    <div
        v-if="goal"
        class="pp-goaltoast"
        role="alert"
        :style="{ '--tc': goal.team?.color || 'var(--accent)' }"
    >
        <div class="gt-burst" aria-hidden="true">
            <span v-for="i in 8" :key="i" :style="{ '--i': i - 1 }" />
        </div>
        <span class="gt-word display">GOAL!</span>
        <div class="gt-info">
            <Crest :team="goal.team" :size="30" ring />
            <div class="gt-text">
                <b>{{ goal.scorer || goal.team?.name }}</b>
                <span class="mono"
                    ><template v-if="goal.minute != null"
                        >{{ goal.minute }}' · </template
                    >{{ goal.scoreline }}</span
                >
            </div>
        </div>
        <IcBall :size="22" />
    </div>
</template>
