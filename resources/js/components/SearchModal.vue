<script setup>
import { useStorage } from '@vueuse/core';
import { computed, nextTick, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

import Crest from '@/components/Crest.vue';
import {
    IcArrowR,
    IcClock,
    IcClose,
    IcSearch,
    IcTrophy,
} from '@/components/icons';
import { useSearchIndex } from '@/composables/useSearchIndex';
import { FEATURED } from '@/lib/featured';

const props = defineProps({
    open: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);

const router = useRouter();
const { index, load } = useSearchIndex({ immediate: false });

const q = ref('');
const sel = ref(0);
const input = ref(null);
const recent = useStorage('pp_recent_searches', []);

const results = computed(() => {
    const needle = q.value.trim().toLowerCase();

    if (!needle) {
        return [];
    }

    return index.value
        .filter((r) => r.name?.toLowerCase().includes(needle))
        .slice(0, 10);
});

// "Jump to competition" surfaces the marquee competitions first (World Cup,
// Champions League, …) in FEATURED order, topped up with anything else.
const competitions = computed(() => {
    const comps = index.value.filter((r) => r.kind === 'Competition');
    const byId = new Map(comps.map((c) => [c.id, c]));
    const featured = FEATURED.map((code) => byId.get(code)).filter(Boolean);
    const rest = comps.filter((c) => !FEATURED.includes(c.id));

    return [...featured, ...rest].slice(0, 6);
});

// Keep the highlighted row valid whenever the result set changes.
watch(results, () => (sel.value = 0));

// Reset + focus on open; lock body scroll while the palette is up.
watch(
    () => props.open,
    (isOpen) => {
        document.body.style.overflow = isOpen ? 'hidden' : '';

        if (isOpen) {
            load(); // lazy-build the search index the first time search opens
            q.value = '';
            sel.value = 0;
            nextTick(() => input.value?.focus());
        }
    },
);

const close = () => emit('close');

const remember = (term) => {
    const value = (term || '').trim();

    if (!value) {
        return;
    }

    recent.value = [value, ...recent.value.filter((r) => r !== value)].slice(
        0,
        6,
    );
};

const go = (result) => {
    if (!result) {
        return;
    }

    remember(q.value || result.name);
    router.push(result.route);
    close();
};

const clear = () => {
    q.value = '';
    input.value?.focus();
};

const onKey = (e) => {
    if (e.key === 'Escape') {
        e.preventDefault();
        close();
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        sel.value = Math.min(
            Math.max(results.value.length - 1, 0),
            sel.value + 1,
        );
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        sel.value = Math.max(0, sel.value - 1);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        go(results.value[sel.value]);
    }
};
</script>

<template>
    <div v-if="open" class="pp-modal-overlay" @mousedown.self="close">
        <div
            class="pp-searchmodal"
            role="dialog"
            aria-modal="true"
            aria-label="Search"
            @keydown="onKey"
        >
            <div class="sm-input">
                <IcSearch
                    :size="20"
                    style="color: var(--text-muted); flex: none"
                />
                <input
                    ref="input"
                    v-model="q"
                    placeholder="Search teams, competitions…"
                    aria-label="Search query"
                />
                <button
                    v-if="q"
                    class="sm-clear"
                    type="button"
                    aria-label="Clear search"
                    @click="clear"
                >
                    <IcClose :size="16" />
                </button>
                <button class="sm-esc" type="button" @click="close">esc</button>
            </div>

            <div class="sm-body">
                <!-- Idle: recent searches + jump-to-competition shortcuts -->
                <template v-if="!q">
                    <template v-if="recent.length">
                        <div class="sm-sec-label">
                            <IcClock :size="13" /> Recent searches
                        </div>
                        <div class="sm-chips">
                            <button
                                v-for="r in recent"
                                :key="r"
                                class="pp-chip"
                                type="button"
                                @click="q = r"
                            >
                                {{ r }}
                            </button>
                        </div>
                    </template>

                    <div
                        class="sm-sec-label"
                        :style="recent.length ? 'margin-top: 18px' : ''"
                    >
                        <IcTrophy :size="13" /> Jump to competition
                    </div>
                    <div class="sm-comp-grid">
                        <button
                            v-for="c in competitions"
                            :key="c.id"
                            class="sm-comp"
                            type="button"
                            @click="go(c)"
                        >
                            <span
                                class="sm-ic"
                                :style="{ background: c.color, color: '#fff' }"
                            >
                                <IcTrophy :size="14" />
                            </span>
                            <span>{{ c.short || c.name }}</span>
                        </button>
                    </div>
                </template>

                <!-- Query with no matches -->
                <div v-else-if="!results.length" class="sm-empty">
                    <IcSearch :size="24" style="color: var(--text-faint)" />
                    <div>
                        No results for “<b>{{ q }}</b
                        >”
                    </div>
                    <span>Try a team or competition name.</span>
                </div>

                <!-- Results -->
                <div v-else class="sm-results" role="listbox">
                    <button
                        v-for="(r, i) in results"
                        :key="r.kind + r.id"
                        class="sm-row"
                        :class="{ on: i === sel }"
                        role="option"
                        :aria-selected="i === sel"
                        type="button"
                        @mouseenter="sel = i"
                        @click="go(r)"
                    >
                        <Crest
                            v-if="r.kind === 'Team'"
                            :team="r.team"
                            :size="28"
                        />
                        <span
                            v-else
                            class="sm-ic"
                            :style="{ background: r.color, color: '#fff' }"
                        >
                            <IcTrophy :size="15" />
                        </span>
                        <span class="sm-name">{{ r.name }}</span>
                        <span class="sm-kind">{{ r.kind }}</span>
                        <span class="sm-enter"><IcArrowR :size="15" /></span>
                    </button>
                </div>
            </div>

            <div class="sm-foot">
                <span><kbd>↑</kbd><kbd>↓</kbd> navigate</span>
                <span><kbd>↵</kbd> open</span>
                <span><kbd>esc</kbd> close</span>
            </div>
        </div>
    </div>
</template>
