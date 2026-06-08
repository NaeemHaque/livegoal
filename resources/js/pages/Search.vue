<script setup>
import { useStorage } from '@vueuse/core';
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import Crest from '@/components/Crest.vue';
import {
    IcArrowR,
    IcClock,
    IcClose,
    IcSearch,
    IcTrophy,
} from '@/components/icons';
import StateBlock from '@/components/states/StateBlock.vue';
import { useSearchIndex } from '@/composables/useSearchIndex';

const router = useRouter();
const { index, loading } = useSearchIndex();

const q = ref('');
const input = ref(null);
const recent = useStorage('pp_recent_searches', []);

onMounted(() => input.value?.focus());

const results = computed(() => {
    const needle = q.value.trim().toLowerCase();

    if (!needle) {
        return [];
    }

    return index.value.filter((r) => r.name?.toLowerCase().includes(needle));
});

const grouped = computed(() => {
    const groups = { Competition: [], Team: [] };

    for (const r of results.value) {
        groups[r.kind]?.push(r);
    }

    // Cap each group independently so a long list of one kind can't crowd out the other.
    return [
        { kind: 'Competitions', items: groups.Competition.slice(0, 12) },
        { kind: 'Teams', items: groups.Team.slice(0, 16) },
    ].filter((g) => g.items.length);
});

const browse = computed(() =>
    index.value.filter((r) => r.kind === 'Competition').slice(0, 6),
);

const remember = (term) => {
    const value = term.trim();

    if (!value) {
        return;
    }

    recent.value = [value, ...recent.value.filter((r) => r !== value)].slice(
        0,
        6,
    );
};

const open = (result) => {
    remember(q.value || result.name);
    router.push(result.route);
};
</script>

<template>
    <div
        class="pp-page pp-searchpage pp-rise"
        style="max-width: 720px; margin-inline: auto"
    >
        <div class="pp-pagehead">
            <div><h1>Search</h1></div>
        </div>

        <div class="sp-input">
            <IcSearch :size="22" style="color: var(--text-muted)" />
            <input
                ref="input"
                v-model="q"
                type="search"
                placeholder="Teams, competitions…"
                aria-label="Search teams and competitions"
            />
            <button
                v-if="q"
                class="pp-iconbtn sm"
                type="button"
                aria-label="Clear search"
                @click="q = ''"
            >
                <IcClose :size="16" />
            </button>
        </div>

        <!-- Idle: recent searches + browse competitions -->
        <div v-if="!q" style="margin-top: 22px">
            <template v-if="recent.length">
                <div class="pp-section-head">
                    <span class="sh-title"
                        ><IcClock :size="15" /> Recent searches</span
                    ><span class="sh-line" />
                </div>
                <div
                    style="
                        display: flex;
                        flex-wrap: wrap;
                        gap: 8px;
                        margin-bottom: 24px;
                    "
                >
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

            <div class="pp-section-head">
                <span class="sh-title"
                    ><IcTrophy :size="15" /> Browse competitions</span
                ><span class="sh-line" />
            </div>
            <div class="pp-grid cols-3">
                <div
                    v-for="c in browse"
                    :key="c.id"
                    class="pp-playerrow"
                    role="button"
                    tabindex="0"
                    @click="open(c)"
                    @keydown.enter="open(c)"
                    @keydown.space.prevent="open(c)"
                >
                    <span class="sr-tile" :style="{ background: c.color }"
                        ><IcTrophy :size="14"
                    /></span>
                    <span class="pr-name" style="font-size: 13px">{{
                        c.name
                    }}</span>
                </div>
            </div>
        </div>

        <!-- No results -->
        <StateBlock
            v-else-if="!loading && !results.length"
            title="No results"
            :text="`Nothing matches “${q}”. Try a team or competition name.`"
        >
            <template #icon><IcSearch :size="26" /></template>
        </StateBlock>

        <!-- Results -->
        <div v-else style="margin-top: 20px">
            <section
                v-for="group in grouped"
                :key="group.kind"
                style="margin-bottom: 18px"
            >
                <div class="pp-section-head">
                    <span class="sh-title" style="font-size: 14px">{{
                        group.kind
                    }}</span>
                    <span class="sh-count">{{ group.items.length }}</span>
                    <span class="sh-line" />
                </div>
                <div style="display: flex; flex-direction: column; gap: 6px">
                    <div
                        v-for="r in group.items"
                        :key="r.id"
                        class="pp-playerrow"
                        role="button"
                        tabindex="0"
                        @click="open(r)"
                        @keydown.enter="open(r)"
                        @keydown.space.prevent="open(r)"
                    >
                        <Crest
                            v-if="r.kind === 'Team'"
                            :team="r.team"
                            :size="28"
                        />
                        <span
                            v-else
                            class="sr-tile"
                            :style="{ background: r.color }"
                            ><IcTrophy :size="14"
                        /></span>
                        <span class="pr-name">{{ r.name }}</span>
                        <span
                            style="margin-left: auto; color: var(--text-faint)"
                            ><IcArrowR :size="16"
                        /></span>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.sr-tile {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    color: #fff;
    flex: none;
}
</style>
