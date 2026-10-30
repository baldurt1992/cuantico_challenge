import "../css/app.css";
import { createInertiaApp } from "@inertiajs/vue3";
import ui from "@nuxt/ui/vue-plugin";
import { createApp, h, type DefineComponent } from "vue";

createInertiaApp({
    title: (title) => `${title} · Entregas`,
    resolve: async (name) => {
        const pages = import.meta.glob<{ default: DefineComponent }>(
            "./Pages/**/*.vue",
        );
        const load = pages[`./Pages/${name}.vue`];
        if (!load) throw new Error("La página solicitada no está disponible.");
        return (await load()).default;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ui)
            .mount(el);
    },
});
