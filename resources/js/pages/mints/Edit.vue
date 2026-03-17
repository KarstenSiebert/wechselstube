<script setup lang="ts">
import { ref, computed } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { append } from '@/routes/mints';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import FlashMessage from "@/components/FlashMessage.vue";
import { route } from 'ziggy-js';
import "@inertiajs/core"

const props = defineProps<{
    flash?: {
        success?: string
        error?: string
    }
    mint: {
        policy_id: string
        asset_name: string
        asset_hex: string
        ticker: string
        fingerprint: string
        description: string | null
        present: number
        decimals: number
        logo_url?: string | null
    }
}>()

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "mint_token",
        href: append().url,
    },
];

const page = usePage()

const flashMessage = ref(page.props.flash?.success || page.props.flash?.error || '');

function setFlashFromErrors(errors: Record<string, string | string[]>) {
    flashMessage.value = Object.values(errors)
        .map(val => (Array.isArray(val) ? val[0] : val))
        .join(' ');
}

const displayValue = computed({
    get() {
        if (!form.number || form.number === '0') return '';

        const intVal = BigInt(form.number);
        const scale = BigInt(10) ** BigInt(form.decimals);

        // Ganze Zahl
        const intPart = intVal / scale;
        // Rest als Fractional
        const fracPart = intVal % scale;
        // Ganze Zahl + Fractional als Float
        const value = Number(intPart) + Number(fracPart) / Math.pow(10, form.decimals);

        // Locale format
        return value.toLocaleString(undefined, {
            minimumFractionDigits: form.decimals,
            maximumFractionDigits: form.decimals,
        });
    },

    set(value: string) {
        const s = value.replace(',', '.').replace(/[^0-9]/g, '');
        if (s === '') {
            form.number = '';
            return;
        }

        // Kleinste Einheit speichern
        form.number = s;
    },
});

const displayPresent = computed(() =>
    (form.present / Math.pow(10, form.decimals)).toLocaleString(undefined, {
        minimumFractionDigits: form.decimals > 6 ? 6 : form.decimals,
        maximumFractionDigits: form.decimals > 6 ? 6 : form.decimals,
    })
);

const form = useForm({
    name: props.mint.asset_name,
    ticker: props.mint.ticker,
    number: '',
    present: props.mint.present,
    policy_id: props.mint.policy_id,
    asset_hex: props.mint.asset_hex,
    asset_name: props.mint.asset_name,
    fingerprint: props.mint.fingerprint,
    decimals: props.mint.decimals
})

function submitForm() {
    if (form.number) {
        form.number = BigInt(form.number).toString();
    }

    form.post(route('mints.mint'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            flashMessage.value = page.props.flash?.success || 'Minted successfully!';
        },
        onError: () => {
            setFlashFromErrors(form.errors);
        }
    });
}

</script>

<template>

    <Head :title="$t('mint_token')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="relative max-w-4xl text-xs flex flex-1 flex-col gap-4 overflow-x-auto rounded-xl px-4 pt-5 pb-4 bg-white dark:bg-gray-900 shadow">

            <div class="absolute top-2 left-1/2 -translate-x-1/2 z-20 w-full max-w-sm">
                <FlashMessage type="success" :message="page.props.flash?.success ? $t(page.props.flash.success) : ''" />
                <FlashMessage type="error" :message="page.props.flash?.error ? $t(page.props.flash.error) : ''" />
            </div>

            <form @submit.prevent="submitForm" class="p-6 bg-gray-50 dark:bg-gray-800 rounded-lg shadow">

                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $t('mint_token') }}
                    </h2>
                </div>

                <div class="mb-3 flex gap-4">
                    <div class="flex flex-col flex-1">
                        <label for="name" class="block text-sm font-medium mb-1">{{
                            $t('name') }}</label>
                        <input id="name" :value="form.name" type="text"
                            class="font-mono w-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 
                  bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 cursor-default focus:outline-none focus:ring-0" readonly autocomplete="off" />
                        <div class="text-red-600 h-5"> </div>
                    </div>

                    <div class="flex flex-col flex-1">
                        <label for="ticker" class="block text-sm font-medium mb-1">{{
                            $t('ticker') }}</label>
                        <input id="ticker" :value="form.ticker" type="text"
                            class="font-mono w-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 
                  bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 cursor-default focus:outline-none focus:ring-0" readonly autocomplete="off" />
                        <div class="text-red-600 h-5"> </div>
                    </div>
                </div>

                <div class="mb-3 flex gap-4">
                    <!-- Number input -->
                    <div class="flex flex-col flex-1">
                        <label for="number" class="block text-sm font-medium mb-1">
                            {{ $t('additional_number') }}
                        </label>
                        <input id="number" v-model="displayValue" type="text"
                            class="font-mono w-full px-1 py-1 rounded border border-blue-500 focus:ring-2 focus:ring-blue-400 dark:focus:ring-blue-300 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-200 placeholder-gray-400"
                            required autocomplete="off" />
                        <div class="text-red-600 h-5">
                            {{ form.errors.number || '' }}
                        </div>
                    </div>

                    <div class="flex flex-col flex-1">
                        <label for="present" class="block text-sm font-medium mb-1">
                            {{ $t('present_number') }}
                        </label>
                        <input id="present" :value="displayPresent" type="text"
                            class="w-full py-1 px-1 rounded border font-mono border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 cursor-default focus:outline-none focus:ring-0"
                            autocomplete="off" readonly />
                        <div class="text-red-600 h-5"> </div>
                    </div>
                </div>

                <div class="mb-3 flex gap-4">
                    <div class="flex flex-col flex-1">
                        <label for="policy_id" class="block text-sm font-medium mb-1">{{
                            $t('policy_id') }}</label>
                        <input id="policy_id" :value="form.policy_id" type="text"
                            class="font-mono w-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 
                  bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 cursor-default focus:outline-none focus:ring-0" readonly autocomplete="off" />
                        <div class="text-red-600 h-5"> </div>
                    </div>

                    <div class="flex flex-col flex-1">
                        <label for="fingerprint" class="block text-sm font-medium mb-1">{{
                            $t('fingerprint') }}</label>
                        <input id="link" :value="form.fingerprint" type="text"
                            class="font-mono w-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 
                  bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 cursor-default focus:outline-none focus:ring-0" readonly autocomplete="off" />
                        <div class="text-red-600 h-5">
                        </div>
                    </div>
                </div>

                <div class="h-5"></div>

                <div class="mt-4 flex justify-end">
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 dark:hover:bg-blue-500 disabled:opacity-50 cursor-pointer"
                        :disabled="form.processing">
                        {{ $t('token_mint') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>