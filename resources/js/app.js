import { createInertiaApp } from '@inertiajs/vue3';
import { ZiggyVue } from 'ziggy-js';

createInertiaApp({
    withApp(app, { page }) {
        const ziggy = page.props.ziggy;

        app.use(
            ZiggyVue,
            ziggy
                ? { ...ziggy, location: new URL(ziggy.location) }
                : undefined,
        );
    },
});
