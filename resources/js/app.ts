import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const pages = import.meta.glob('./pages/**/*.vue');

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),

    resolve: async (name) => {
        const page = await resolvePageComponent(`./pages/${name}.vue`, pages);

        return typeof page === 'function' ? await page() : page;
    },

    setup({ el, App, props, plugin }) {
        const vueApp = createApp({
            render: () => h(App, props),
        });

        vueApp.directive('focus', {
            mounted: (element: HTMLElement, binding) => {
                if (binding.value !== false) {
                    element.focus();
                }
            },
        });

        vueApp.use(plugin).mount(el);
    },

    progress: {
        color: '#4B5563',
    },
});
