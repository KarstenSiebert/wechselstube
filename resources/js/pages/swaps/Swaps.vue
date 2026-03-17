<script setup lang="ts">
import { ref } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { index } from '@/routes/swaps';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import FlashMessage from "@/components/FlashMessage.vue";

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'swaps', href: index().url },
];

const page = usePage();
const searchQuery = ref("");
const form = useForm({});

function submitForm() {
    form.get("/swaps/create");
}
</script>

<template>

    <Head :title="$t('swap_offers')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="relative max-w-4xl text-xs flex flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4 bg-white dark:bg-gray-900 shadow">
            <div class="absolute top-2 left-1/2 -translate-x-1/2 z-20 w-full max-w-sm">
                <FlashMessage type="success" :message="page.props.flash?.success ? $t(page.props.flash.success) : ''" />
                <FlashMessage type="error" :message="page.props.flash?.error ? $t(page.props.flash.error) : ''" />
            </div>

            <form @submit.prevent="submitForm" class="p-6 bg-gray-50 dark:bg-gray-800 rounded-lg shadow">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $t('swap_offers') }}
                    </h2>
                    <input v-model="searchQuery" id="search" type="text" :placeholder="$t('search...')"
                        class="ml-4 px-3 py-2 border rounded-lg text-sm w-64 dark:bg-gray-700 dark:text-gray-200" />
                </div>
            </form>
        </div>
    </AppLayout>
</template>
