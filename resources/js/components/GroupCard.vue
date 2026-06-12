<script setup>
import { computed } from 'vue';

import TeamChip from '@/components/TeamChip.vue';

const props = defineProps({
    label: { type: String, required: true }, // e.g. "Group A"
    rows: { type: Array, default: () => [] },
});

const emit = defineEmits(['team']);

const letter = computed(
    () => props.label.replace(/group\s*/i, '').trim() || props.label,
);
</script>

<template>
    <div class="pp-groupcard">
        <div class="gc-head">
            <span class="gc-letter display">{{ letter }}</span>
            <span class="gc-title">{{ label }}</span>
        </div>
        <table class="pp-groupmini">
            <thead>
                <tr>
                    <th></th>
                    <th class="l">Team</th>
                    <th>P</th>
                    <th>GD</th>
                    <th>Pts</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(r, i) in rows"
                    :key="r.team?.id ?? i"
                    :class="{ qual: i < 2 }"
                    style="cursor: pointer"
                    tabindex="0"
                    @click="emit('team', r.team?.id)"
                    @keydown.enter="emit('team', r.team?.id)"
                    @keydown.space.prevent="emit('team', r.team?.id)"
                >
                    <td class="pos">
                        <span class="qdot" :class="{ on: i < 2 }">{{
                            i + 1
                        }}</span>
                    </td>
                    <td class="l">
                        <TeamChip :team="r.team" :size="18" code />
                    </td>
                    <td class="tnum">{{ r.played }}</td>
                    <td class="tnum">
                        {{ r.goalDifference > 0 ? '+' : ''
                        }}{{ r.goalDifference }}
                    </td>
                    <td class="tnum pts">{{ r.points }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
