import { createApp } from 'vue';

import App from '@/App.vue';
import router from '@/router';

const app = createApp(App).use(router);

app.mount('#app');

// Fade out the inline boot loader once the app is mounted and the first route
// has resolved, holding a minimum display so it never just flashes.
router.isReady().then(() => {
    const loader = document.getElementById('pp-loader');

    if (!loader) {
        return;
    }

    // Keep the loader long enough to avoid a flash on slow loads, but don't tax
    // fast loads — the old 1100ms floor delayed first paint on every visit (LCP).
    const MIN_DISPLAY_MS = 250;
    const remaining = Math.max(0, MIN_DISPLAY_MS - performance.now());

    setTimeout(() => {
        loader.classList.add('pp-loader-hide');
        loader.addEventListener('transitionend', () => loader.remove(), {
            once: true,
        });
        // Fallback removal in case transitionend doesn't fire (after the 0.7s fade).
        setTimeout(() => loader.remove(), 900);
    }, remaining);
});
