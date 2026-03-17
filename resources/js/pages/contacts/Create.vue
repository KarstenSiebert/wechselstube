<script setup lang="ts">
import { ref, reactive, watch } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { create } from '@/routes/contacts';
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
        title: "create_contact",
        href: create().url,
    },
];

interface User {
    id: number
    name?: string | null
    email?: string | null
    wallet_address?: string | null
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
    id: 0,
    name: '',
    address: ''
})

const containerRef = ref<HTMLElement | null>(null);

const page = usePage()

const flashMessage = ref(page.props.flash?.success || page.props.flash?.error || '');

function setFlashFromErrors(errors: Record<string, string | string[]>) {
    flashMessage.value = Object.values(errors)
        .map(val => (Array.isArray(val) ? val[0] : val))
        .join(' ');
}

function submitForm() {
    form.post("/contacts", {
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

    <Head :title="$t('create_contact')" />
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
                        {{ $t('create_contact') }}
                    </h2>
                </div>

                <!-- Name -->
                <div ref="containerRef" class="mb-3 relative w-full">
                    <label for="name" class="block text-sm font-medium mb-1">{{ $t('name') }}</label>
                    <input id="name" type="text" v-model="form.name" @input="handleInputEvent"
                        :placeholder="$t('type_to_search_user')"
                        class="w-full font-mono py-1 px-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        autocomplete="off" />

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

                <!-- Wallet Address -->
                <div class="mb-3">
                    <label for="address" class="block text-sm font-medium mb-1">{{ $t('wallet_address') }}</label>
                    <input id="address" v-model="form.address" type="text"
                        class="w-full font-mono px-1 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        required autocomplete="off" />
                    <div class="text-red-600 h-5">
                        {{ form.errors.address || '' }}
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 dark:hover:bg-blue-500 disabled:opacity-50 cursor-pointer"
                        :disabled="form.processing">
                        {{ $t('save_contact') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>