<script setup lang="ts">
import { ref, computed, watch } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { index } from '@/routes/mints';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
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
    { title: "mints", href: index().url },
];

type TxRow = {
    id: number
    policy_id: string | null
    asset_name: string
    asset_hex: string
    fingerprint: string
    quantity: number
    ticker: number | null
    description: string | null
    decimals: number
    logo_url?: string
}

type Pagination<T> = {
    data: T[]
    current_page: number
    last_page: number
}

const props = defineProps<{
    mints: Pagination<TxRow>
}>()

const mint = ref<Pagination<TxRow> | undefined>(props.mints)

const editableMints = ref<TxRow[]>([])

watch(
    () => props.mints,
    (newMints) => {
        mint.value = newMints
        if (newMints?.data?.length) {
            editableMints.value = newMints.data.map(a => ({
                id: Number(a.id),
                policy_id: a.policy_id,
                asset_name: a.asset_name,
                asset_hex: a.asset_hex,
                fingerprint: a.fingerprint,
                ticker: a.ticker,
                description: a.description,
                quantity: Number(a.quantity),
                decimals: Number(a.decimals),
                logo_url: a.logo_url
            }))
        }
    },
    { immediate: true }
)

const page = usePage()

const form = useForm({
    selected_assets: [] as TxRow[],
})

const searchQuery = ref("")

const filteredMints = computed(() => {
    if (!searchQuery.value) return sortedMints.value

    const query = searchQuery.value.toLowerCase().replace(',', '.')

    return sortedMints.value.filter(p => {
        const quantity = p.asset_name === 'ADA'
            ? p.quantity / 1e6
            : p.quantity / Math.pow(10, p.decimals)
        const quantityStr = quantity.toString().toLowerCase()

        return (
            p.asset_name.toLowerCase().includes(query) ||
            p.fingerprint.toLowerCase().includes(query) ||
            quantityStr.includes(query)
        )
    })
})

const sortField = ref<keyof TxRow>("asset_name")
const sortAsc = ref(true)

function sort(field: keyof TxRow) {
    if (sortField.value === field) sortAsc.value = !sortAsc.value
    else {
        sortField.value = field
        sortAsc.value = true
    }
}

const sortedMints = computed(() => {
    return [...editableMints.value].sort((a, b) => {
        const valA = a[sortField.value] ?? (sortField.value === "asset_name" ? 0 : "")
        const valB = b[sortField.value] ?? (sortField.value === "asset_name" ? 0 : "")

        if (valA < valB) return sortAsc.value ? -1 : 1
        if (valA > valB) return sortAsc.value ? 1 : -1
        return 0
    })
})

const pagesToShow = computed<(number | string)[]>(() => {
    if (!mint.value) return []
    const total = mint.value.last_page
    const current = mint.value.current_page
    const delta = 2
    const range: number[] = []
    const rangeWithDots: (number | string)[] = []
    let last: number | undefined

    for (let i = 1; i <= total; i++) {
        if (i === 1 || i === total || (i >= current - delta && i <= current + delta)) {
            range.push(i)
        }
    }

    for (const i of range) {
        if (last !== undefined) {
            if (i - last === 2) rangeWithDots.push(last + 1)
            else if (i - last > 2) rangeWithDots.push('...')
        }
        rangeWithDots.push(i)
        last = i
    }

    return rangeWithDots
})

function goTo(pageNum: number) {
    router.get('/mints', { page: pageNum }, { preserveScroll: true })
}

function submitForm() {
    form.get("/mints/create")
}

function mintToken(asset: TxRow) {
    form.selected_assets = [asset]

    form.post("/mints/append")

    // router.get(`/mints/${asset.id}/edit`, asset, { preserveScroll: true });
}

function burnToken(asset: TxRow) {
    router.delete(`/mints/${asset.id}`, { data: asset, preserveScroll: true });
}

</script>

<template>

    <Head :title="$t('minted_assets')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="relative max-w-4xl text-xs flex flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4 bg-white dark:bg-gray-900 shadow">
            <!-- Flash Messages -->
            <div class="absolute top-2 left-1/2 -translate-x-1/2 z-20 w-full max-w-sm">
                <FlashMessage type="success" :message="page.props.flash?.success ? $t(page.props.flash.success) : ''" />
                <FlashMessage type="error" :message="page.props.flash?.error ? $t(page.props.flash.error) : ''" />
            </div>

            <!-- Form -->
            <form @submit.prevent="submitForm" class="p-6 bg-gray-50 dark:bg-gray-800 rounded-lg shadow">
                <!-- Header + Search -->
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $t('minted_assets') }}
                    </h2>
                    <input v-model="searchQuery" id="search" type="text" :placeholder="$t('search...')"
                        class="ml-4 px-3 py-2 border rounded-lg text-sm w-64 dark:bg-gray-700 dark:text-gray-200" />
                </div>

                <!-- Table -->
                <div class="overflow-x-auto rounded-lg shadow">
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer"
                                    @click="sort('asset_name')" style="width: 15% !important">
                                    {{ $t('name') }}
                                </th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer"
                                    @click="sort('quantity')" style="width: 20% !important">
                                    {{ $t('number') }}
                                </th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer"
                                    @click="sort('fingerprint')" style="width: 54% !important">
                                    {{ $t('fingerprint') }}
                                </th>
                                <th class="px-3 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default"
                                    style="width: 11% !important">
                                    {{ $t('actions') }}
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="asset in filteredMints" :key="asset.id"
                                class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800">
                                <td
                                    class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200 truncate max-w-xs cursor-default">
                                    <component :is="asset.fingerprint ? 'a' : 'div'"
                                        :href="asset.fingerprint ? 'https://cexplorer.io/asset/' + asset.fingerprint : null"
                                        target="_blank" rel="noopener noreferrer"
                                        class="flex items-center space-x-2 group transition-shadow duration-200 rounded">
                                        <img v-if="asset.logo_url" :src="asset.logo_url" alt="logo"
                                            class="w-6 h-6 rounded transition-transform duration-200"
                                            :class="{ 'group-hover:scale-105': asset.fingerprint }" />
                                        <span
                                            class="transition-colors duration-200 truncate cursor-pointer max-w-xs overflow-hidden text-ellipsis"
                                            :class="{ 'group-hover:text-blue-600': asset.fingerprint }">
                                            <tt>{{ asset.asset_name }}</tt>
                                        </span>
                                    </component>
                                </td>

                                <td
                                    class="pr-8 py-2 text-sm text-right text-gray-900 dark:text-gray-200 cursor-default max-w-xs overflow-hidden">
                                    <tt>{{
                                        (asset.asset_name === "ADA"
                                            ? asset.quantity / 1e6
                                            : asset.quantity / Math.pow(10, asset.decimals)
                                        ).toLocaleString(undefined, {
                                            minimumFractionDigits: asset.decimals > 6 ? 6 : asset.decimals,
                                            maximumFractionDigits: asset.decimals > 6 ? 6 : asset.decimals
                                        })
                                    }}</tt>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200">
                                    <component :is="asset.fingerprint ? 'a' : 'div'"
                                        :href="asset.fingerprint ? 'https://cexplorer.io/asset/' + asset.fingerprint : null"
                                        target="_blank" rel="noopener noreferrer"
                                        class="flex items-center space-x-2 group transition-shadow duration-200 rounded">
                                        <span
                                            class="transition-colors truncate duration-200 overflow-hidden text-ellipsis"
                                            :class="{ 'group-hover:text-blue-600': asset.fingerprint }">
                                            <tt>{{ asset.fingerprint }}</tt>
                                        </span>
                                    </component>
                                </td>

                                <td class="px-3 py-2 text-center flex items-center justify-center gap-2">
                                    <button type="button" @click="mintToken(asset)"
                                        class="text-green-500 hover:text-green-700 dark:hover:text-green-400 p-1 cursor-pointer">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="3" stroke-linecap="round"
                                            stroke-linejoin="round" class="w-4 h-4">
                                            <path d="m14 13-8.381 8.38a1 1 0 0 1-3.001-3L11 9.999" />
                                            <path
                                                d="M15.973 4.027A13 13 0 0 0 5.902 2.373c-1.398.342-1.092 2.158.277 2.601a19.9 19.9 0 0 1 5.822 3.024" />
                                            <path
                                                d="M16.001 11.999a19.9 19.9 0 0 1 3.024 5.824c.444 1.369 2.26 1.676 2.603.278A13 13 0 0 0 20 8.069" />
                                            <path
                                                d="M18.352 3.352a1.205 1.205 0 0 0-1.704 0l-5.296 5.296a1.205 1.205 0 0 0 0 1.704l2.296 2.296a1.205 1.205 0 0 0 1.704 0l5.296-5.296a1.205 1.205 0 0 0 0-1.704z" />
                                        </svg>
                                    </button>

                                    <button type="button" @click="burnToken(asset)"
                                        class="text-red-500 hover:text-red-700 dark:hover:text-red-400 p-1 cursor-pointer">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="currentColor"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4m-4 0a1 1 0 00-1 1v1h6V4a1 1 0 00-1-1m-4 0h4" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="!filteredMints.length">
                                <td colspan="4" class="text-center py-4 text-gray-500">
                                    {{ $t('no_assets_found') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div v-if="filteredMints.length > 0 && mint?.last_page && mint.last_page > 1"
                        class="flex justify-center mt-4 mb-4 space-x-1">
                        <button type="button" v-if="mint.current_page > 1" @click="goTo(mint.current_page - 1)"
                            class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300">
                            {{ $t('prev') }}
                        </button>

                        <template v-for="pageNum in pagesToShow" :key="pageNum">
                            <span v-if="pageNum === '...'" class="px-3 py-1 text-gray-500 select-none">...</span>
                            <button type="button" v-else @click="goTo(Number(pageNum))" class="px-3 py-1 border rounded"
                                :class="pageNum === mint.current_page ? 'bg-blue-500 text-white' : 'bg-gray-200 hover:bg-gray-300'">
                                {{ pageNum }}
                            </button>
                        </template>

                        <button type="button" v-if="mint.current_page < mint.last_page"
                            @click="goTo(mint.current_page + 1)"
                            class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300">
                            {{ $t('next') }}
                        </button>
                    </div>
                </div>

                <div class="h-5"></div>

                <!-- Submit Button -->
                <div class="mt-4 flex justify-end">
                    <button type="submit"
                        class="px-4 py-2 rounded-lg shadow cursor-pointer text-white bg-blue-600 hover:bg-blue-700 dark:hover:bg-blue-500 disabled:bg-gray-400 disabled:cursor-not-allowed disabled:opacity-70"
                        :disabled="form.processing">
                        {{ $t('mint_token') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
