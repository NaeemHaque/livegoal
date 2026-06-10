<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import Crest from '@/components/Crest.vue';
import { useTimeFormat } from '@/composables/useTimeFormat';

const props = defineProps({
    rounds: { type: Array, default: () => [] },
});

const emit = defineEmits(['open']);

const time = useTimeFormat();

const rootRef = ref(null);
const lines = ref([]);
let observer = null;
let frame = 0;

const winner = (t) => {
    if (t.hs == null || t.as == null || t.hs === t.as) {
        return null;
    }

    return t.hs > t.as ? 'home' : 'away';
};
const label = (team) => team?.tla || team?.short || team?.name || 'TBD';

// Measure the rendered nodes and derive elbow connectors between each round and
// the next. Two parent ties feed one child tie, so a column is only connected
// when it has exactly twice the next column's node count — which also skips the
// trailing standalone third-place node.
function measure() {
    const root = rootRef.value;

    if (!root) {
        return;
    }

    const rootRect = root.getBoundingClientRect();
    const columns = [...root.querySelectorAll('.b-col')].map((col) =>
        [...col.querySelectorAll('.pp-bnode')].map((node) => {
            const r = node.getBoundingClientRect();

            return {
                left: r.left - rootRect.left,
                right: r.right - rootRect.left,
                cy: r.top - rootRect.top + r.height / 2,
            };
        }),
    );

    const segments = [];

    for (let ci = 0; ci < columns.length - 1; ci++) {
        const parents = columns[ci];
        const children = columns[ci + 1];

        if (!children.length || parents.length !== children.length * 2) {
            continue;
        }

        for (let j = 0; j < children.length; j++) {
            const a = parents[2 * j];
            const b = parents[2 * j + 1];
            const c = children[j];
            const midX = (a.right + c.left) / 2;

            segments.push(
                // Two parent stubs out to the gutter, the vertical that joins the
                // pair, and the stub into the child.
                { x1: a.right, y1: a.cy, x2: midX, y2: a.cy },
                { x1: b.right, y1: b.cy, x2: midX, y2: b.cy },
                {
                    x1: midX,
                    y1: Math.min(a.cy, c.cy),
                    x2: midX,
                    y2: Math.max(b.cy, c.cy),
                },
                { x1: midX, y1: c.cy, x2: c.left, y2: c.cy },
            );
        }
    }

    lines.value = segments;
}

function scheduleMeasure() {
    cancelAnimationFrame(frame);
    frame = requestAnimationFrame(measure);
}

onMounted(() => {
    nextTick(measure);

    if (typeof ResizeObserver !== 'undefined' && rootRef.value) {
        observer = new ResizeObserver(scheduleMeasure);
        observer.observe(rootRef.value);
    }
});

onBeforeUnmount(() => {
    cancelAnimationFrame(frame);
    observer?.disconnect();
});

watch(
    () => props.rounds,
    () => nextTick(measure),
);
</script>

<template>
    <div ref="rootRef" class="pp-bracket">
        <svg class="b-lines" aria-hidden="true">
            <line
                v-for="(s, i) in lines"
                :key="i"
                :x1="s.x1"
                :y1="s.y1"
                :x2="s.x2"
                :y2="s.y2"
            />
        </svg>
        <div v-for="(rd, ri) in rounds" :key="ri" class="b-col">
            <div class="b-col-title">{{ rd.title }}</div>
            <div class="b-col-body">
                <div
                    v-for="(t, i) in rd.ties"
                    :key="t.id ?? i"
                    class="pp-bnode"
                    :class="{ live: t.live }"
                    :role="t.id ? 'button' : undefined"
                    :tabindex="t.id ? 0 : -1"
                    @click="t.id && emit('open', t.id)"
                    @keydown.enter="t.id && emit('open', t.id)"
                    @keydown.space.prevent="t.id && emit('open', t.id)"
                >
                    <div v-if="t.kickoff" class="bn-time">
                        {{ time.shortDateTime(t.kickoff) }}
                    </div>
                    <div
                        class="bn-row"
                        :class="{
                            w: winner(t) === 'home',
                            l: winner(t) === 'away',
                        }"
                    >
                        <Crest v-if="t.home" :team="t.home" :size="18" />
                        <span class="bn-name" :class="{ tbd: !t.home }">{{
                            t.home ? label(t.home) : 'TBD'
                        }}</span>
                        <span class="bn-score tnum">{{ t.hs ?? '–' }}</span>
                    </div>
                    <div
                        class="bn-row"
                        :class="{
                            w: winner(t) === 'away',
                            l: winner(t) === 'home',
                        }"
                    >
                        <Crest v-if="t.away" :team="t.away" :size="18" />
                        <span class="bn-name" :class="{ tbd: !t.away }">{{
                            t.away ? label(t.away) : 'TBD'
                        }}</span>
                        <span class="bn-score tnum">{{ t.as ?? '–' }}</span>
                    </div>
                    <span v-if="t.live" class="bn-live"
                        ><span class="d" />LIVE</span
                    >
                </div>
            </div>
        </div>
    </div>
</template>
