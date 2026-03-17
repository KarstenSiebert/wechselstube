// import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { ZiggyVue } from 'ziggy-js';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { DefineComponent, createApp, h } from 'vue';
import { i18nVue } from 'laravel-vue-i18n';
import type { TranslationSchema } from './types/lang';
import { initializeTheme } from './composables/useAppearance';
import axios from 'axios';

const appName = import.meta.env.VITE_APP_NAME || 'Wechselstuben';

declare const Ziggy: any

const langs = import.meta.glob<{ default: TranslationSchema }>('../../lang/*.json', { eager: true });

export function getAvailableLanguages() {
    return Object.keys(langs).map(k => k.match(/([^/]+)\.json$/)![1]);
}

export function loadLanguage(lang: string): TranslationSchema {
    const fileKey = Object.keys(langs).find((k) => k.endsWith(`${lang}.json`));
    if (!fileKey) throw new Error(`Language file for "${lang}" not found`);
    return langs[fileKey].default;
}

const initialLang = document.documentElement.lang || 'en';

axios.defaults.headers.common['X-User-Locale'] = initialLang

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue, Ziggy)
            .use(i18nVue, {
                lang: initialLang,
                resolve: (lang: string) => loadLanguage(lang)
            })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
