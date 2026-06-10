<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';

import Crest from '@/components/Crest.vue';
import { IcAlert, IcChevL, IcGlobe, IcUsers } from '@/components/icons';
import InlineLoader from '@/components/InlineLoader.vue';
import ErrorState from '@/components/states/ErrorState.vue';
import { useBack } from '@/composables/useBack';
import { usePageMeta } from '@/composables/usePageMeta';
import { usePerson } from '@/composables/usePerson';

const props = defineProps({ id: { type: String, required: true } });
const router = useRouter();
const goBack = useBack();

const id = computed(() => props.id);
const { data: player, loading, error, reload } = usePerson(id);

usePageMeta(() => player.value?.name || null);

const parts = computed(() =>
    (player.value?.dateOfBirth ?? '').split('-').map(Number),
);

const age = computed(() => {
    const [y, m, d] = parts.value;

    if (!y) {
        return null;
    }

    const now = new Date();
    let years = now.getFullYear() - y;

    if (
        now.getMonth() + 1 < m ||
        (now.getMonth() + 1 === m && now.getDate() < d)
    ) {
        years -= 1;
    }

    return years;
});

const born = computed(() => {
    const [y, m, d] = parts.value;

    if (!y) {
        return null;
    }

    return new Date(y, m - 1, d).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
});

const position = computed(() => {
    const p = player.value?.position;

    return p && p !== 'null' ? p : null;
});

const facts = computed(() => {
    const p = player.value;

    if (!p) {
        return [];
    }

    return [
        ['position', position.value],
        ['shirt number', p.shirtNumber != null ? `#${p.shirtNumber}` : null],
        ['nationality', p.nationality],
        ['date of birth', born.value],
        ['age', age.value != null ? `${age.value} yrs` : null],
        ['team', p.team?.name],
    ].filter(([, v]) => v);
});

const openTeam = () =>
    player.value?.team?.id && router.push(`/team/${player.value.team.id}`);
</script>

<template>
    <div class="pp-page pp-rise">
        <button
            class="pp-btn ghost sm"
            type="button"
            style="margin-bottom: 14px"
            @click="goBack"
        >
            <IcChevL :size="15" /> Back
        </button>

        <InlineLoader
            v-if="loading && !player"
            label="Loading player"
            :min-height="200"
        />
        <ErrorState v-else-if="error" @retry="reload" />

        <template v-else-if="player">
            <div class="pp-entity-head">
                <div
                    class="eh-glow"
                    style="
                        background: radial-gradient(
                            100% 140% at 0% 0%,
                            var(--accent),
                            transparent 60%
                        );
                    "
                />
                <div class="eh-main">
                    <div class="pp-avatar">
                        <IcUsers :size="34" style="color: var(--text-faint)" />
                        <span
                            v-if="player.team"
                            class="av-crest"
                            role="button"
                            tabindex="0"
                            @click="openTeam"
                            @keydown.enter="openTeam"
                            @keydown.space.prevent="openTeam"
                        >
                            <Crest :team="player.team" :size="22" />
                        </span>
                    </div>
                    <div>
                        <h1>{{ player.name }}</h1>
                        <div class="eh-sub">
                            <span v-if="player.nationality"
                                ><IcGlobe :size="13" />
                                {{ player.nationality }}</span
                            >
                            <span v-if="position || player.shirtNumber != null">
                                <IcUsers :size="13" /> {{ position
                                }}<template
                                    v-if="
                                        position && player.shirtNumber != null
                                    "
                                >
                                    · </template
                                ><template v-if="player.shirtNumber != null"
                                    >#{{ player.shirtNumber }}</template
                                >
                            </span>
                            <span v-if="age != null">{{ age }} yrs</span>
                            <span
                                v-if="player.team"
                                role="button"
                                tabindex="0"
                                style="cursor: pointer"
                                @click="openTeam"
                                @keydown.enter="openTeam"
                                @keydown.space.prevent="openTeam"
                            >
                                Club: <Crest :team="player.team" :size="14" />
                                {{ player.team.name }}
                            </span>
                        </div>
                    </div>
                </div>
                <div
                    v-if="player.shirtNumber != null || age != null"
                    class="eh-stats"
                >
                    <div v-if="player.shirtNumber != null" class="eh-stat">
                        <b class="display tnum">{{ player.shirtNumber }}</b
                        ><small>Shirt</small>
                    </div>
                    <div v-if="age != null" class="eh-stat">
                        <b class="display tnum">{{ age }}</b
                        ><small>Age</small>
                    </div>
                </div>
            </div>

            <div class="pp-section-head">
                <span class="sh-title"><IcUsers :size="16" /> Profile</span
                ><span class="sh-line" />
            </div>
            <dl class="pp-detaillist" style="margin-bottom: 22px">
                <div v-for="[key, value] in facts" :key="key">
                    <dt>{{ key }}</dt>
                    <dd
                        v-if="key === 'team'"
                        role="button"
                        tabindex="0"
                        style="cursor: pointer"
                        @click="openTeam"
                        @keydown.enter="openTeam"
                        @keydown.space.prevent="openTeam"
                    >
                        {{ value }}
                    </dd>
                    <dd v-else>{{ value }}</dd>
                </div>
            </dl>

            <div class="pp-section-head">
                <span class="sh-title">Match &amp; season statistics</span
                ><span class="sh-line" />
            </div>
            <div
                class="pp-chip"
                style="cursor: default; opacity: 0.75; width: max-content"
            >
                <IcAlert :size="13" style="color: var(--draw)" /> Not available
                on the free data plan
            </div>
        </template>
    </div>
</template>

<style scoped>
.pp-avatar {
    position: relative;
    width: 72px;
    height: 72px;
    border-radius: 18px;
    background: var(--surface-3);
    display: grid;
    place-items: center;
    overflow: hidden;
}
.pp-avatar .av-crest {
    position: absolute;
    bottom: 4px;
    right: 4px;
    cursor: pointer;
}
</style>
