<script setup lang="ts">
import { ref, reactive, computed, watch } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { create } from '@/routes/payments';
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
        title: "request_payment",
        href: create().url,
    },
];

interface User {
    id: number
    name?: string | null
    email?: string | null
    wallet_address?: string | null
}

interface BabelFee {
    id: number
    babelfee_token?: string | null
    policy_id?: string | null
    decimals: number | null
    is_CIP68: boolean | null
}

const searchResultsBabelFee = ref<BabelFee[]>([])

const searchCacheBabelFee = reactive<Record<string, BabelFee[]>>({})

async function searchBabelFees(query: string) {
    if (!query) {
        searchResultsBabelFee.value = []
        return
    }

    const prefix = Object.keys(searchCacheBabelFee).find(k => query.startsWith(k))
    const safeIncludes = (field: string | null | undefined, q: string) =>
        (field ?? '').toLowerCase().includes(q.toLowerCase())

    if (prefix) {
        searchResultsBabelFee.value = searchCacheBabelFee[prefix].filter(c =>
            safeIncludes(c.babelfee_token, query) || safeIncludes(c.policy_id, query)
        )
        return
    }

    loading.value = true
    try {
        const res = await fetch(`/babelfees/search?q=${encodeURIComponent(query)}`)
        if (res.ok) {
            const data: BabelFee[] = await res.json()
            searchResultsBabelFee.value = data
            searchCacheBabelFee[query] = data
        }
    } finally {
        loading.value = false
    }
}

function handleInputBabelFee(query: string) {
    clearTimeout(searchTimeout)
    searchTimeout = window.setTimeout(() => {
        searchBabelFees(query)
    }, 300)
}

function handleInputEventBabelFee(e: Event) {
    const target = e.target as HTMLInputElement;
    handleInputBabelFee(target.value);
}

function selectBabelFee(babelfee: BabelFee) {
    form.asset_name = babelfee.babelfee_token || ''
    form.policy_id = babelfee.policy_id || ''
    form.decimals = babelfee.decimals || 0
    searchResultsBabelFee.value = []
}

const searchResults = ref<User[]>([])

const loading = ref(false)

const searchCache = reactive<Record<string, User[]>>({})

let searchTimeout: number | undefined

async function searchUsers(query: string) {
    if (!query) {
        searchResults.value = []
        return
    }

    const prefix = Object.keys(searchCache).find(k => query.startsWith(k))
    const safeIncludes = (field: string | null | undefined, q: string) =>
        (field ?? '').toLowerCase().includes(q.toLowerCase())

    if (prefix) {
        searchResults.value = searchCache[prefix].filter(c =>
            safeIncludes(c.name, query) || safeIncludes(c.wallet_address, query)
        )
        return
    }

    loading.value = true
    try {
        const res = await fetch(`/users/search?q=${encodeURIComponent(query)}`)
        if (res.ok) {
            const data: User[] = await res.json()
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
        searchUsers(query)
    }, 300)
}

function handleInputEvent(e: Event) {
    const target = e.target as HTMLInputElement;
    handleInput(target.value);
}

function selectUser(user: User) {
    form.id = user.id
    form.name = user.name || ''
    form.address = user.wallet_address || ''
    searchResults.value = []
}

const form = useForm({
    id: null as number | null,
    name: '',
    address: '',
    asset_name: '',
    policy_id: '',
    decimals: 0,
    quantity: ''
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

        form.quantity = s;

        form.quantity = String(Number(s));
    },
});

function submitForm() {
    form.post("/payments", {
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

    <Head :title="$t('request_payment')" />
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
                        {{ $t('create_payment_request') }}
                    </h2>
                </div>

                <div class="mb-3 relative">
                    <label for="name" class="block text-sm font-medium mb-1">{{ $t('name') }}</label>
                    <input id="name" v-model="form.name" @input="handleInputEvent" type="text"
                        :placeholder="$t('type_to_search_user')"
                        class="w-full py-1 px-1 rounded border font-mono border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        autocomplete="off" required />

                    <ul v-if="searchResults.length"
                        class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded max-h-60 overflow-auto shadow-lg">
                        <li v-for="(user, index) in searchResults" :key="user.id" @click="selectUser(user)"
                            :class="{ 'bg-gray-200 dark:bg-gray-600': index === user.id }"
                            class="px-2 py-1 font-mono cursor-pointer truncate hover:bg-gray-200 dark:hover:bg-gray-600">
                            {{ user.name }} ({{ user.email }})
                        </li>
                    </ul>
                    <div class="text-red-600 h-5">
                        {{ form.errors.name || '' }}
                    </div>
                </div>

                <div class="mb-3 flex gap-4">
                    <div class="flex flex-col flex-1 relative">
                        <label for="asset_name" class="block text-sm font-medium mb-1">{{ $t('token') }}</label>
                        <!-- relative wrapper around input -->
                        <div class="relative w-full">
                            <input id="asset_name" type="text" v-model="form.asset_name"
                                @input="handleInputEventBabelFee" :placeholder="$t('type_to_search_tokens')" class="w-full py-1 px-1 font-mono rounded border border-gray-300 dark:border-gray-600 
                  bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 
                  focus:outline-none focus:ring-1 focus:ring-blue-500" autocomplete="off" />

                            <ul v-if="searchResultsBabelFee.length" class="absolute left-0 top-full w-full mt-1 bg-white dark:bg-gray-700 
               border border-gray-300 dark:border-gray-600 rounded 
               max-h-60 overflow-auto shadow-lg z-50">
                                <li v-for="babelfee in searchResultsBabelFee" :key="babelfee.id"
                                    @click="selectBabelFee(babelfee)" class="px-2 py-1 font-mono cursor-pointer truncate 
                 hover:bg-gray-200 dark:hover:bg-gray-600">
                                    {{ babelfee.babelfee_token }} ({{ babelfee.policy_id }})
                                </li>
                            </ul>
                        </div>
                        <div class="text-red-600 h-5">
                            {{ form.errors.asset_name || '' }}
                        </div>
                    </div>

                    <div class="flex flex-col flex-1">
                        <label for="quantity" class="block text-sm font-medium mb-1">{{ $t('number') }}</label>
                        <input id="quantity" v-model="displayValue" type="text" class="font-mono w-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 
                  bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200" required autocomplete="off" />
                        <div class="text-red-600 h-5">
                            {{ form.errors.quantity || '' }}
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

                <input type="hidden" v-model="form.id" />

                <div class="mt-4 flex justify-end">
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 dark:hover:bg-blue-500 disabled:opacity-50 cursor-pointer"
                        :disabled="form.processing">
                        {{ $t('save_request') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>