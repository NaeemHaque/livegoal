import { createRouter, createWebHistory } from 'vue-router';

const routes = [
    { path: '/', name: 'live', component: () => import('@/pages/LiveHub.vue') },
    {
        path: '/matches',
        name: 'matches',
        component: () => import('@/pages/Matches.vue'),
    },
    {
        path: '/match/:id',
        name: 'match',
        component: () => import('@/pages/MatchDetail.vue'),
        props: true,
    },
    {
        path: '/competitions',
        name: 'competitions',
        component: () => import('@/pages/Competitions.vue'),
    },
    {
        path: '/competition/:id',
        name: 'competition',
        component: () => import('@/pages/CompetitionDetail.vue'),
        props: true,
    },
    {
        path: '/team/:id',
        name: 'team',
        component: () => import('@/pages/TeamDetail.vue'),
        props: true,
    },
    {
        path: '/player/:id',
        name: 'player',
        component: () => import('@/pages/PlayerDetail.vue'),
        props: true,
    },
    {
        path: '/scorers',
        name: 'scorers',
        component: () => import('@/pages/Scorers.vue'),
    },
    {
        path: '/favorites',
        name: 'favorites',
        component: () => import('@/pages/Favorites.vue'),
    },
    {
        path: '/search',
        name: 'search',
        component: () => import('@/pages/Search.vue'),
    },
    {
        path: '/settings',
        name: 'settings',
        component: () => import('@/pages/Settings.vue'),
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('@/pages/NotFound.vue'),
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

export default router;
