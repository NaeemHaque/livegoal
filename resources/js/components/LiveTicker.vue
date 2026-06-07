<script setup>
import { computed } from 'vue';

const props = defineProps({
    matches: { type: Array, default: () => [] },
});

const live = computed(() => props.matches);
</script>

<template>
    <div v-if="live.length" class="pp-ticker">
        <span class="tk-label"><span class="dot" /> LIVE</span>
        <div class="tk-scroll">
            <div class="tk-track">
                <RouterLink
                    v-for="(m, i) in [...live, ...live]"
                    :key="i"
                    :to="`/match/${m.id}`"
                    class="tk-item"
                >
                    <span class="tk-comp">{{ m.competitionShort }}</span>
                    <span class="tk-teams">
                        {{ m.homeShort
                        }}<span class="tk-sc"
                            >{{ m.homeScore }}–{{ m.awayScore }}</span
                        >{{ m.awayShort }}
                    </span>
                    <span class="tk-min">{{ m.minuteLabel }}</span>
                </RouterLink>
            </div>
        </div>
    </div>
</template>
