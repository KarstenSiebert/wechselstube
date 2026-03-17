<script setup lang="ts">
import { ref, reactive, watch } from "vue";
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
        title: "create_babel_fee",
        href: create().url,
    },
];

interface BabelFee {
    id: number
    babelfee_token?: string | null
    policy_id?: string | null
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
            safeIncludes(c.babelfee_token, query) || safeIncludes(c.policy_id, query)
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
    form.babelfee_token = babelfee.babelfee_token || ''
    form.policy_id = babelfee.policy_id || ''
    form.decimals = babelfee.decimals || 0
    form.is_CIP68 = babelfee.is_CIP68 || false
    searchResults.value = []
}

const form = useForm({
    babelfee_token: '',
    policy_id: '',
    decimals: 0,
    is_CIP68: false,
    rate: '',
})

const page = usePage()

const flashMessage = ref(page.props.flash?.success || page.props.flash?.error || '');

function setFlashFromErrors(errors: Record<string, string | string[]>) {
    flashMessage.value = Object.values(errors)
        .map(val => (Array.isArray(val) ? val[0] : val))
        .join(' ');
}

function submitForm() {
    form.post("/babelfees", {
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

    <Head :title="$t('create_babel_fee')" />
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
                        {{ $t('add_babel_fee') }}
                    </h2>
                </div>

                <div class="mb-3 flex space-x-6">
                    <!-- Left 50% -->
                    <div ref="containerRef" class="relative flex flex-col w-1/2">
                        <label for="babelfee_token" class="block text-sm font-medium mb-1">Token</label>

                        <input id="babelfee_token" type="text" v-model="form.babelfee_token" @input="handleInputEvent"
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
                            {{ form.errors.babelfee_token || '' }}
                        </div>
                    </div>

                    <!-- Right 50% split into 3 -->
                    <div class="flex flex-1 space-x-4">
                        <div class="flex flex-col flex-1">
                            <label for="rate" class="block text-sm font-medium mb-1">{{ $t('rate') }}</label>
                            <input id="rate" v-model="form.rate" type="decimal"
                                class="font-mono w-30 px-1 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200"
                                required autocomplete="off" />
                            <div class="text-red-600 h-5">
                                {{ form.errors.rate || '' }}
                            </div>
                        </div>

                        <div class="flex flex-col flex-1">
                            <label for="decimals" class="block text-sm font-medium mb-1">{{ $t('decimals') }}</label>
                            <input id="decimals" v-model="form.decimals" type="number"
                                class="font-mono w-30 px-1 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200"
                                required autocomplete="off" />
                            <div class="text-red-600 h-5">
                                {{ form.errors.decimals || '' }}
                            </div>
                        </div>

                        <div class="flex flex-col flex-1">
                            <label for="is_CIP68" class="block text-sm font-medium mb-1">CIP-68</label>
                            <input id="is_CIP68" type="checkbox" v-model="form.is_CIP68"
                                class="h-4 w-4 mt-1 text-blue-600 border-gray-300 rounded focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600" />
                            <div class="h-5"></div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="policy_id" class="block text-sm font-medium mb-1">{{ $t('policy_id') }}</label>
                    <input id="policy_id" v-model="form.policy_id" type="text"
                        class="font-mono w-full h-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200"
                        required autocomplete="off" />
                    <div class="text-red-600 h-5">
                        {{ form.errors.policy_id || '' }}
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 dark:hover:bg-blue-500 disabled:opacity-50 cursor-pointer"
                        :disabled="form.processing">
                        {{ $t('save_babel_fee') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>