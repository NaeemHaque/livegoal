<script setup>
import FormGuide from '@/components/FormGuide.vue';
import TeamChip from '@/components/TeamChip.vue';

defineProps({
    rows: { type: Array, default: () => [] },
    compact: { type: Boolean, default: false },
    highlightId: { type: [String, Number], default: null },
});

const emit = defineEmits(['team']);

const zoneClass = (z) => ({ qualify: 'z-ucl', relegation: 'z-rel' })[z] || '';
</script>

<template>
    <div class="pp-standings-wrap">
        <table class="pp-standings" :class="{ cmp: compact }">
            <thead>
                <tr>
                    <th class="c-pos">#</th>
                    <th class="c-team">Team</th>
                    <th>P</th>
                    <template v-if="!compact">
                        <th>W</th>
                        <th>D</th>
                        <th>L</th>
                        <th>GF</th>
                        <th>GA</th>
                    </template>
                    <th>GD</th>
                    <th class="c-pts">Pts</th>
                    <th v-if="!compact" class="c-form">Form</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="r in rows"
                    :key="r.team?.id ?? r.position"
                    :class="[
                        zoneClass(r.zone),
                        {
                            hl:
                                highlightId != null &&
                                String(highlightId) === String(r.team?.id),
                        },
                    ]"
                    style="cursor: pointer"
                    @click="emit('team', r.team?.id)"
                >
                    <td class="c-pos">
                        <span class="pos-mark">{{ r.position }}</span>
                    </td>
                    <td class="c-team">
                        <TeamChip :team="r.team" :size="22" :code="compact" />
                    </td>
                    <td class="tnum">{{ r.played }}</td>
                    <template v-if="!compact">
                        <td class="tnum">{{ r.won }}</td>
                        <td class="tnum">{{ r.draw }}</td>
                        <td class="tnum">{{ r.lost }}</td>
                        <td class="tnum">{{ r.goalsFor }}</td>
                        <td class="tnum">{{ r.goalsAgainst }}</td>
                    </template>
                    <td class="tnum">
                        {{ r.goalDifference > 0 ? '+' : ''
                        }}{{ r.goalDifference }}
                    </td>
                    <td class="c-pts tnum">{{ r.points }}</td>
                    <td v-if="!compact" class="c-form">
                        <FormGuide :form="r.form" :size="18" />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
