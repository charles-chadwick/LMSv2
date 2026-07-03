import { createInertiaApp } from '@inertiajs/vue3';
import { route, ZiggyVue } from 'ziggy-js';

createInertiaApp({
    withApp(app, { page }) {
        const ziggy = page.props.ziggy;
        const config = ziggy
            ? { ...ziggy, location: new URL(ziggy.location) }
            : undefined;

        app.use(ZiggyVue, config);

        // ZiggyVue only exposes route() inside <template> blocks. Expose it
        // globally so route() also resolves in <script setup> handlers, where
        // it is otherwise an undefined reference.
        window.Ziggy = config;
        window.route = route;
    },
});
