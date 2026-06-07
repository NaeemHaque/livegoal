<script setup>
import { useRoute } from 'vue-router';

import {
    IcCalendar,
    IcChart,
    IcLive,
    IcStar,
    IcTrophy,
} from '@/components/icons';

const NAV = [
    { label: 'Live', to: '/', icon: IcLive, active: ['live', 'match'] },
    { label: 'Matches', to: '/matches', icon: IcCalendar, active: ['matches'] },
    {
        label: 'Competitions',
        to: '/competitions',
        icon: IcTrophy,
        active: ['competitions', 'competition'],
    },
    {
        label: 'Following',
        to: '/favorites',
        icon: IcStar,
        active: ['favorites', 'team'],
    },
    {
        label: 'Top Scorers',
        to: '/scorers',
        icon: IcChart,
        active: ['scorers'],
    },
];

const route = useRoute();
const isActive = (item) => item.active.includes(route.name);
</script>

<template>
    <nav class="pp-topnav" aria-label="Primary">
        <div class="pp-topnav-inner">
            <RouterLink
                v-for="item in NAV"
                :key="item.label"
                :to="item.to"
                class="pp-navtab"
                :class="{ on: isActive(item) }"
            >
                <span class="nt-ic"
                    ><component :is="item.icon" :size="18"
                /></span>
                <span class="nt-label">{{ item.label }}</span>
            </RouterLink>
        </div>
    </nav>
</template>
