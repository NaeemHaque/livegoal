<script setup>
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

import FilterTabs from '@/components/FilterTabs.vue';
import FormationLoader from '@/components/FormationLoader.vue';
import EmptyState from '@/components/states/EmptyState.vue';
import ErrorState from '@/components/states/ErrorState.vue';
import TopScorersList from '@/components/TopScorersList.vue';
import { useApi } from '@/composables/useApi';
import { useCompetitions } from '@/composables/useCompetitions';

const router = useRouter();

const { data: competitions } = useCompetitions();
const selected = ref(null);

// Default to the Premier League, then any league, then whatever's first.
watch(
    competitions,
    (list) => {
        if (selected.value || !list?.length) {
            return;
        }

        const pick =
            list.find((c) => (c.code || c.id) === 'PL') ??
            list.find((c) => c.kind === 'league') ??
            list[0];
        selected.value = String(pick.code || pick.id);
    },
    { immediate: true },
);

const {
    data: scorers,
    loading,
    error,
    reload,
} = useApi(() =>
    selected.value ? `/competitions/${selected.value}/scorers` : null,
);

const tabs = computed(() =>
    (competitions.value ?? []).map((c) => ({
        id: String(c.code || c.id),
        label: c.short || c.name,
    })),
);
const current = computed(() =>
    (competitions.value ?? []).find(
        (c) => String(c.code || c.id) === selected.value,
    ),
);

const openPlayer = (id) => id && router.push(`/player/${id}`);
const openTeam = (id) => id && router.push(`/team/${id}`);
</script>

<template>
    <div class="pp-page pp-rise" style="max-width: 760px; margin-inline: auto">
        <div class="pp-pagehead">
            <div>
                <h1>Top Scorers</h1>
                <div class="ph-sub">
                    {{
                        current
                            ? `${current.name} · Golden Boot race`
                            : 'Golden Boot races'
                    }}
                </div>
            </div>
        </div>

        <FilterTabs
            v-if="tabs.length && selected"
            v-model="selected"
            :tabs="tabs"
            style="margin-bottom: 18px"
        />

        <FormationLoader
            v-if="loading && !scorers"
            label="Loading top scorers"
        />

        <ErrorState v-else-if="error" @retry="reload" />

        <EmptyState
            v-else-if="!scorers?.length"
            title="No scorers yet"
            text="Top scorers appear once the competition is underway."
        />

        <TopScorersList
            v-else
            :scorers="scorers"
            @player="openPlayer"
            @team="openTeam"
        />
    </div>
</template>
