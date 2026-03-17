<script setup lang="ts">
import { ref, computed, watch } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { edit, update } from '@/routes/payments';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import FlashMessage from "@/components/FlashMessage.vue";

const props = defineProps<{
    flash?: {
        success?: string
        error?: string
    }
    payment: {
        id: number
        name: string
        address: string
        asset_name: string
        policy_id: string
        fingerprint: string
        quantity: string
        decimals: number
        updated_at: string
    }
}>()

// Breadcrumbs
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "update_request",
        href: edit(props.payment.id).url,
    },
];

const page = usePage()

const form = useForm({
    quantity: props.payment.quantity,
    decimals: props.payment.decimals
})

const displayValue = computed({
    get() {
        if (!form.quantity || form.quantity === '0') return '';

        const intVal = BigInt(form.quantity);
        const scale = BigInt(10) ** BigInt(form.decimals);

        const intPart = intVal / scale;

        const fracPart = intVal % scale;

        const value = Number(intPart) + Number(fracPart) / Math.pow(10, form.decimals);

        return value.toLocaleString(undefined, {
            minimumFractionDigits: form.decimals,
            maximumFractionDigits: form.decimals,
        });
    },

    set(value: string) {
        const s = value.replace(',', '.').replace(/[^0-9]/g, '');
        if (s === '') {
            form.quantity = '';
            return;
        }

        form.quantity = String(Number(s));
    },
});


// Flash message handling
const flashMessage = ref(props.flash?.success || props.flash?.error || '')

function setFlashFromErrors(errors: Record<string, string | string[]>) {
    flashMessage.value = Object.values(errors)
        .map(val => (Array.isArray(val) ? val[0] : val))
        .join(' ')
}

// Submit form using PUT to update
function submitForm() {
    form.put(update(props.payment.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            flashMessage.value = props.flash?.success || 'Saved successfully!';
        },
        onError: () => {
            setFlashFromErrors(form.errors);
        }
    })
}

watch(
    () => props.flash,
    (newFlash) => {
        if (newFlash?.success) flashMessage.value = newFlash.success;
        if (newFlash?.error) flashMessage.value = newFlash.error;
    }
);

</script>

<template>

    <Head :title="$t('update_request')" />
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
                        {{ $t('update_payment_request') }}
                    </h2>
                </div>
                <div class="mb-3 flex space-x-12">
                    <div class="flex flex-col flex-1">
                        <label for="name" class="block text-sm font-medium mb-1">{{ $t('name') }}</label>
                        <input id="name" :value="props.payment.name" type="text"
                            class="font-mono w-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 cursor-default focus:outline-none focus:ring-0"
                            readonly autocomplete="off" />
                        <div class="text-red-600 h-5">
                        </div>
                    </div>
                    <div class="flex flex-col flex-1">
                        <label for="last_update" class="block text-sm font-medium mb-1">{{ $t('last_update') }}</label>
                        <input id="last_update" :value="new Date(props.payment.updated_at).toLocaleString()" type="text"
                            class="font-mono w-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 cursor-default focus:outline-none focus:ring-0"
                            readonly autocomplete="off" />
                        <div class="text-red-600 h-5">
                        </div>
                    </div>
                </div>

                <div class="mb-3 flex space-x-12">
                    <div class="flex flex-col flex-1">
                        <label for="asset_name" class="block text-sm font-medium mb-1">{{ $t('token') }}</label>
                        <input id="asset_name" :value="props.payment.asset_name" type="text"
                            class="font-mono w-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 cursor-default focus:outline-none focus:ring-0"
                            readonly />
                        <div class="text-red-600 h-5">
                        </div>
                    </div>

                    <div class="flex flex-col flex-1">
                        <label for="quantity" class="block text-sm font-medium mb-1">{{ $t('value') }}</label>
                        <input id="quantity" v-model="displayValue" type="text"
                            class="font-mono w-full px-1 py-1 rounded border border-blue-500 focus:ring-2 focus:ring-blue-400 dark:focus:ring-blue-300 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-200 placeholder-gray-400"
                            required autocomplete="off" />
                        <div class="text-red-600 h-5">
                            {{ form.errors.quantity || '' }}
                        </div>
                    </div>
                </div>

                <div class="mb-3 flex space-x-12">
                    <div class="flex flex-col flex-1">
                        <label for="policy_id" class="block text-sm font-medium mb-1">{{ $t('policy_id') }}</label>
                        <input id="policy_id" :value="props.payment.policy_id" type="text"
                            class="font-mono w-full h-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 cursor-default focus:outline-none focus:ring-0"
                            readonly />
                    </div>
                    <div class="flex flex-col flex-1">
                        <label for="fingerprint" class="block text-sm font-medium mb-1">{{ $t('fingerprint') }}</label>
                        <input id="fingerprint" :value="props.payment.fingerprint" type="text"
                            class="font-mono w-full h-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 cursor-default focus:outline-none focus:ring-0"
                            readonly />
                    </div>
                </div>

                <div class="text-red-600 h-5"></div>
                <div class="mt-4 flex justify-end">
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 dark:hover:bg-blue-500 disabled:opacity-50 cursor-pointer"
                        :disabled="form.processing">
                        {{ $t('update_request') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
