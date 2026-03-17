<script setup lang="ts">
import { ref, watch } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { edit, update } from '@/routes/contacts';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import FlashMessage from "@/components/FlashMessage.vue";

const props = defineProps<{
    flash?: {
        success?: string
        error?: string
    }
    contact: {
        id: number
        name: string
        address: string
    }
}>()

// Breadcrumbs
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "update_contact",
        href: edit(props.contact.id).url,
    },
];

const page = usePage()

// Form initialization with existing babelfee values
const form = useForm({
    name: props.contact.name,
    address: props.contact.address,
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
    form.put(update(props.contact.id).url, {
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

    <Head :title="$t('update_contact')" />
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
                        {{ $t('update_contact') }}
                    </h2>
                </div>

                <div class="mb-3">
                    <label for="name" class="block text-sm font-medium mb-1">{{ $t('name') }}</label>
                    <input id="name" :value="props.contact.name" type="text"
                        class="font-mono w-full h-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 cursor-default focus:outline-none focus:ring-0"
                        readonly autocomplete="off" />
                    <div class="text-red-600 h-5">
                        {{ form.errors.name || '' }}
                    </div>
                </div>

                <div class="mb-3">
                    <label for="address" class="block text-sm font-medium mb-1">{{ $t('wallet_address') }}</label>
                    <input id="address" v-model="form.address" type="text"
                        class="font-mono w-full h-full px-1 py-1 rounded border border-blue-500 focus:ring-2 focus:ring-blue-400 dark:focus:ring-blue-300 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-200 placeholder-gray-400"
                        required autocomplete="off" />
                    <div class="text-red-600 h-5">
                        {{ form.errors.address || '' }}
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 dark:hover:bg-blue-500 disabled:opacity-50 cursor-pointer"
                        :disabled="form.processing">
                        {{ $t('update_contact') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
