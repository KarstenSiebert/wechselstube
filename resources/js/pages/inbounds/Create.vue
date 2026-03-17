<script setup lang="ts">
import { ref, reactive, computed, watch } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { create } from '@/routes/babelfees';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import FlashMessage from "@/components/FlashMessage.vue";
import "@inertiajs/core"

declare module "@inertiajs/core" {
    interface PageProps {
        flash: {
            success?: string
            error?: string
        }
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "add_inbound",
        href: create().url,
    },
];

interface BabelFee {
    id: number
    babelfee_token?: string | null
    policy_id?: string | null
    fingerprint?: string | null
    decimals: number
    is_CIP68: boolean
}

const searchResults = ref<BabelFee[]>([])

const loading = ref(false)

const searchCache = reactive<Record<string, BabelFee[]>>({})

let searchTimeout: number | undefined

async function searchBabelFees(query: string) {
    if (!query) {
        searchResults.value = []
        return
    }

    const prefix = Object.keys(searchCache).find(k => query.startsWith(k))
    const safeIncludes = (field: string | null | undefined, q: string) =>
        (field ?? '').toLowerCase().includes(q.toLowerCase())

    if (prefix) {
        searchResults.value = searchCache[prefix].filter(c =>
            safeIncludes(c.babelfee_token, query) || safeIncludes(c.fingerprint, query)
        )
        return
    }

    loading.value = true
    try {
        const res = await fetch(`/babelfees/search?q=${encodeURIComponent(query)}`)
        if (res.ok) {
            const data: BabelFee[] = await res.json()
            searchResults.value = data
            searchCache[query] = data
        }
    } finally {
        loading.value = false
    }
}

function handleInput(query: string) {
    clearTimeout(searchTimeout)
    searchTimeout = window.setTimeout(() => {
        searchBabelFees(query)
    }, 300)
}

function handleInputEvent(e: Event) {
    const target = e.target as HTMLInputElement;
    handleInput(target.value);
}

function selectBabelFee(babelfee: BabelFee) {
    form.inbound_token = babelfee.babelfee_token || ''
    form.policy_id = babelfee.policy_id || ''
    form.fingerprint = babelfee.fingerprint || ''
    form.decimals = babelfee.decimals || 0
    form.is_CIP68 = babelfee.is_CIP68 || false
    searchResults.value = []
}

const form = useForm({
    inbound_token: '',
    policy_id: '',
    fingerprint: '',
    location: '',
    decimals: 0,
    is_CIP68: false,
    cost: '',
})

const page = usePage()

const flashMessage = ref(page.props.flash?.success || page.props.flash?.error || '');

function setFlashFromErrors(errors: Record<string, string | string[]>) {
    flashMessage.value = Object.values(errors)
        .map(val => (Array.isArray(val) ? val[0] : val))
        .join(' ');
}

const displayValue = computed({
    get() {
        if (!form.cost || form.cost === '0') return '';

        const intVal = BigInt(form.cost);
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
            form.cost = '';
            return;
        }

        form.cost = s;

        form.cost = String(Number(s));
    },
});

function submitForm() {
    form.post("/inbounds", {
        preserveScroll: true,
        onSuccess: () => {
            form.reset()
            flashMessage.value = page.props.flash?.success || 'Saved successfully!';
        },
        onError: () => {
            setFlashFromErrors(form.errors);
        }
    })
}

watch(
    () => page.props.flash,
    (newFlash) => {
        if (newFlash.success) flashMessage.value = newFlash.success;
        if (newFlash.error) flashMessage.value = newFlash.error;
    }
);

</script>

<template>

    <Head :title="$t('create_inbound')" />
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
                        {{ $t('add_inbound') }}
                    </h2>
                </div>

                <div class="mb-3 flex space-x-6">
                    <!-- Left 50% -->
                    <div ref="containerRef" class="relative flex flex-col w-1/2">
                        <label for="inbound_token" class="block text-sm font-medium mb-1">Token</label>

                        <input id="inbound_token" type="text" v-model="form.inbound_token" @input="handleInputEvent"
                            :placeholder="$t('type_to_search_tokens')"
                            class="w-full py-1 px-1 font-mono rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            autocomplete="off" />

                        <ul v-if="searchResults.length"
                            class="absolute top-full left-0 z-50 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded max-h-60 overflow-auto shadow-lg">

                            <li v-for="(babelfee, index) in searchResults" :key="babelfee.id"
                                @click="selectBabelFee(babelfee)"
                                :class="{ 'bg-gray-200 dark:bg-gray-600': index === babelfee.id }"
                                class="px-2 py-1 font-mono cursor-pointer truncate hover:bg-gray-200 dark:hover:bg-gray-600">
                                {{ babelfee.babelfee_token }} ({{ babelfee.policy_id }})
                            </li>
                        </ul>

                        <div class="text-red-600 h-5">
                            {{ form.errors.inbound_token || '' }}
                        </div>
                    </div>

                    <!-- Right 50% split into 2 -->
                    <div class="flex flex-1 space-x-4">
                        <div class="flex flex-col flex-1">
                            <label for="cost" class="block text-sm font-medium mb-1">{{ $t('cost') }}</label>
                            <input id="cost" v-model="displayValue" type="text"
                                class="font-mono w-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200"
                                required autocomplete="off" />
                            <div class="text-red-600 h-5">
                                {{ form.errors.cost || '' }}
                            </div>
                        </div>

                    </div>
                </div>

                <div class="mb-3">
                    <label for="fingerprint" class="block text-sm font-medium mb-1">{{ $t('fingerprint') }}</label>
                    <input id="fingerprint" v-model="form.fingerprint" type="text"
                        class="font-mono w-full h-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 cursor-default focus:outline-none focus:ring-0"
                        readonly autocomplete="off" />
                    <div class="text-red-600 h-5">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="location" class="block text-sm font-medium mb-1">{{ $t('location') }}</label>
                    <input id="location" v-model="form.location" type="text"
                        class="font-mono w-full h-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200"
                        required autocomplete="off" />
                    <div class="text-red-600 h-5">
                        {{ form.errors.location || '' }}
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 dark:hover:bg-blue-500 disabled:opacity-50 cursor-pointer"
                        :disabled="form.processing">
                        {{ $t('save_inbound') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>