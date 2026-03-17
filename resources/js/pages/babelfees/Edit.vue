<script setup lang="ts">
import { ref, computed, watch } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { edit, update } from '@/routes/babelfees';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import FlashMessage from "@/components/FlashMessage.vue";

const props = defineProps<{
    flash?: {
        success?: string
        error?: string
    }
    babelfee: {
        id: number
        babelfee_token: string
        policy_id: string
        fingerprint: string
        rate: string | number
        decimals: string | number
        is_CIP68: boolean
    }
}>()

// Breadcrumbs
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "update_babel_fee",
        href: edit(props.babelfee.id).url,
    },
];

const page = usePage()

// Form initialization with existing babelfee values
const form = useForm({
    rate: props.babelfee.rate,
    decimals: props.babelfee.decimals
})

// Flash message handling
const flashMessage = ref(props.flash?.success || props.flash?.error || '')

function setFlashFromErrors(errors: Record<string, string | string[]>) {
    flashMessage.value = Object.values(errors)
        .map(val => (Array.isArray(val) ? val[0] : val))
        .join(' ')
}

// Submit form using PUT to update
function submitForm() {
    form.put(update(props.babelfee.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            flashMessage.value = props.flash?.success || 'Saved successfully!';
        },
        onError: () => {
            setFlashFromErrors(form.errors);
        }
    })
}

function handleRateInput(event: Event) {
    const input = event.target as HTMLInputElement;

    let value = String(input.value);

    value = value.replace(',', '.');

    value = value.replace(/[^\d.]/g, '');

    const parts = value.split('.');

    if (parts.length > 2) {
        form.errors.rate = 'Invalid number format'
        return
    }

    if (parts[1]) {
        parts[1] = parts[1].slice(0, 6);
    }

    form.rate = parts.join('.');
    form.decimals = parts[1] ? parts[1].length : 0;
    form.errors.rate = ''
}

const displayValue = computed({
    get() {
        return String(form.rate).includes('.') ? String(form.rate).replace('.', ',') : form.rate
    },
    set(value: string) {
        handleRateInput({ target: { value } } as unknown as Event)
    }
})

watch(
    () => props.flash,
    (newFlash) => {
        if (newFlash?.success) flashMessage.value = newFlash.success;
        if (newFlash?.error) flashMessage.value = newFlash.error;
    }
);

</script>

<template>

    <Head :title="$t('update_babel_fee')" />
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
                        {{ $t('update_babel_fee') }}
                    </h2>
                </div>

                <div class="mb-3 flex space-x-6">
                    <!-- Left 50% -->
                    <div ref="containerRef" class="relative flex flex-col w-1/2">
                        <label for="babelfee_token" class="block text-sm font-medium mb-1">{{ $t('token') }}</label>
                        <input id="babelfee_token" :value="props.babelfee.babelfee_token" type="text"
                            class="font-mono w-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 cursor-default focus:outline-none focus:ring-0"
                            readonly autocomplete="off" />
                        <div class="text-red-600 h-5">
                        </div>
                    </div>

                    <div class="flex flex-1 space-x-4">
                        <div class="flex flex-col flex-1">
                            <label for="rate" class="block text-sm font-medium mb-1">{{ $t('rate') }}</label>
                            <input id="rate" :value="displayValue" @input="handleRateInput" type="text"
                                class="font-mono w-full px-1 py-1 rounded border border-blue-500 focus:ring-2 focus:ring-blue-400 dark:focus:ring-blue-300 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-200 placeholder-gray-400"
                                :placeholder="$t('enter_new_rate')" required autocomplete="off" />
                            <div class="text-red-600 h-5">
                                {{ form.errors.rate || '' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="fingerprint" class="block text-sm font-medium mb-1">{{ $t('fingerprint') }}</label>
                    <input id="fingerprint" :value="props.babelfee.fingerprint" type="text"
                        class="font-mono w-full h-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 cursor-default focus:outline-none focus:ring-0"
                        readonly autocomplete="off" />
                    <div class="h-5"></div>
                </div>

                <div class="mt-4 flex justify-end">
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 dark:hover:bg-blue-500 disabled:opacity-50 cursor-pointer"
                        :disabled="form.processing">
                        {{ $t('update_rate') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
