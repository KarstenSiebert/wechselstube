<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';
import { loadLanguageAsync, trans } from 'laravel-vue-i18n';

import HeadingSmall from '@/components/HeadingSmall.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { languages } from '@/routes';

import { getAvailableLanguages } from '@/app';

import usFlag from '@/assets/flags/us.svg';
import deFlag from '@/assets/flags/de.svg';
import frFlag from '@/assets/flags/fr.svg';
import esFlag from '@/assets/flags/es.svg';
import jpFlag from '@/assets/flags/jp.svg';
import inFlag from '@/assets/flags/in.svg';

const flags: Record<string, string> = {
    de: deFlag,
    jp: jpFlag,
    es: esFlag,
    en: usFlag,
    fr: frFlag,
    hi: inFlag,
};

const breadcrumbItems: BreadcrumbItem[] = [
    { title: "language_settings", href: languages().url },
];

const availableLanguages = getAvailableLanguages();

const selectedLanguage = ref(document.documentElement.lang || 'en');

function changeLanguage(lang: string) {
    selectedLanguage.value = lang;

    axios.defaults.headers.common['X-User-Locale'] = lang;

    router.post('/language', { locale: lang }, {
        preserveScroll: true,
        onFinish: () => {
            // window.location.reload();
            loadLanguageAsync(lang);
        }
    });
}

</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">

        <Head :title="trans('language_settings')" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall :title="trans('language_settings')"
                    :description="trans('update_your_accounts_language_settings')" />


                <div class="flex space-x-2">
                    <label v-for="lang in availableLanguages" :key="lang" class="relative cursor-pointer">
                        <!-- Hidden radio input -->
                        <input type="radio" :value="lang" v-model="selectedLanguage"
                            @change="() => changeLanguage(lang)" class="sr-only" />

                        <!-- Pill -->
                        <span class="relative flex items-center justify-center w-10 h-10 select-none">
                            <!-- Flag -->
                            <img v-if="flags[lang]" :src="flags[lang]" alt="" class="object-contain" />

                            <!-- Checkmark overlay -->
                            <svg v-if="selectedLanguage === lang"
                                class="absolute top-0 right-0 w-4 h-4 text-white bg-blue-800 rounded-full p-0.5"
                                fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 00-1.414 0L8 12.586 4.707 9.293a1 1 0 00-1.414 1.414l4 4a1 1 0 001.414 0l8-8a1 1 0 000-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </span>
                    </label>
                </div>

            </div>
        </SettingsLayout>
    </AppLayout>
</template>
