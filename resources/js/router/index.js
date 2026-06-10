import { createRouter, createWebHistory } from 'vue-router';

const SITE = 'LiveGoal';

const routes = [
    {
        path: '/',
        name: 'live',
        component: () => import('@/pages/LiveHub.vue'),
        meta: { title: 'Live Football Scores' },
    },
    {
        path: '/matches',
        name: 'matches',
        component: () => import('@/pages/Matches.vue'),
        meta: { title: 'Fixtures & Results' },
    },
    {
        path: '/match/:id',
        name: 'match',
        component: () => import('@/pages/MatchDetail.vue'),
        props: true,
        meta: { title: 'Match Centre' },
    },
    {
        path: '/competitions',
        name: 'competitions',
        component: () => import('@/pages/Competitions.vue'),
        meta: { title: 'Competitions' },
    },
    {
        path: '/competition/:id',
        name: 'competition',
        component: () => import('@/pages/CompetitionDetail.vue'),
        props: true,
        meta: { title: 'Competition' },
    },
    {
        path: '/team/:id',
        name: 'team',
        component: () => import('@/pages/TeamDetail.vue'),
        props: true,
        meta: { title: 'Team' },
    },
    {
        path: '/player/:id',
        name: 'player',
        component: () => import('@/pages/PlayerDetail.vue'),
        props: true,
        meta: { title: 'Player' },
    },
    {
        path: '/scorers',
        name: 'scorers',
        component: () => import('@/pages/Scorers.vue'),
        meta: { title: 'Top Scorers' },
    },
    {
        path: '/favorites',
        name: 'favorites',
        component: () => import('@/pages/Favorites.vue'),
        meta: { title: 'Following' },
    },
    {
        path: '/search',
        name: 'search',
        component: () => import('@/pages/Search.vue'),
        meta: { title: 'Search' },
    },
    {
        path: '/settings',
        name: 'settings',
        component: () => import('@/pages/Settings.vue'),
        meta: { title: 'Settings' },
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('@/pages/NotFound.vue'),
        meta: { title: 'Page Not Found' },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

// Keep the tab title in sync on client-side navigation. The first resolution is
// skipped so the server-rendered (entity-specific) title survives the initial
// load; detail pages then refine the generic per-route title via usePageMeta.
let initialNavigation = true;
router.afterEach((to) => {
    if (initialNavigation) {
        initialNavigation = false;
        return;
    }

    document.title = to.meta?.title ? `${to.meta.title} | ${SITE}` : SITE;
});

export default router;
