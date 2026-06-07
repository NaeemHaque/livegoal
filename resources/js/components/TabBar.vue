<script setup>
import { useRoute } from 'vue-router';

import {
    IcCalendar,
    IcLive,
    IcMore,
    IcStar,
    IcTrophy,
} from '@/components/icons';

const TABS = [
    { label: 'Live', to: '/', icon: IcLive, active: ['live', 'match'] },
    { label: 'Matches', to: '/matches', icon: IcCalendar, active: ['matches'] },
    {
        label: 'Comps',
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
    { label: 'More', to: '/settings', icon: IcMore, active: ['settings'] },
];

const route = useRoute();
const isActive = (item) => item.active.includes(route.name);
</script>

<template>
    <nav class="pp-tabbar" aria-label="Primary mobile">
        <RouterLink
            v-for="item in TABS"
            :key="item.label"
            :to="item.to"
            class="tab"
            :class="{ on: isActive(item) }"
        >
            <span class="tb-ic"><component :is="item.icon" :size="22" /></span>
            <span>{{ item.label }}</span>
        </RouterLink>
    </nav>
</template>
