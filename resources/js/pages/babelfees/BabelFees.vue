<script setup lang="ts">
import { ref, computed, onMounted, watch } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { index } from '@/routes/babelfees';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { Dialog, DialogTrigger, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter, DialogClose } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import FlashMessage from "@/components/FlashMessage.vue";
import "@inertiajs/core";

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
        title: 'Babel Fees',
        href: index().url,
    },
];

interface BabelFee {
    id: number,
    babelfee_token: string,
    fingerprint: string,
    rate: number,
    logo_url?: string
}

const props = defineProps<{
    babelfees: {
        data: BabelFee[]
        links: { url: string | null, label: string, active: boolean }[]
        meta: { current_page: number, last_page: number, per_page: number, total: number }
    }
}>()

const page = usePage()

const form = useForm({
    selected_babelfees: [] as BabelFee[],
})

const editableBabelFees = ref<BabelFee[]>(
    props.babelfees.data.map((a) => ({
        ...a,
        id: a.id,
        babelfee_token: a.babelfee_token,
        fingerprint: a.fingerprint,
        rate: a.rate,
        logo_url: a.logo_url ?? 'https://www.wechselstuben.net/storage/logos/wechselstuben-logo.png',
    }))
)

const sortField = ref<keyof BabelFee>("babelfee_token")
const sortAsc = ref(true)

const selected = ref<string[]>([])

function sort(field: keyof BabelFee) {
    if (sortField.value === field) {
        sortAsc.value = !sortAsc.value
    } else {
        sortField.value = field
        sortAsc.value = true
    }
}

const sortedBabelFees = computed(() => {
    return [...editableBabelFees.value].sort((a, b) => {
        const valA = a[sortField.value] ?? (sortField.value === "babelfee_token" ? 0 : "")
        const valB = b[sortField.value] ?? (sortField.value === "babelfee_token" ? 0 : "")

        if (valA < valB) return sortAsc.value ? -1 : 1
        if (valA > valB) return sortAsc.value ? 1 : -1
        return 0
    })
})

const searchQuery = ref("")

const filteredBabelFees = computed(() => {
    if (!searchQuery.value) return sortedBabelFees.value

    const query = searchQuery.value.toLowerCase()

    return sortedBabelFees.value.filter(p =>
        p.babelfee_token.toLowerCase().includes(query) ||
        p.fingerprint.toLowerCase().includes(query) ||
        String(p.rate).toLowerCase().includes(query)
    )
})

watch(selected, () => {
    if (!selectAllCheckbox.value) return
    const total = sortedBabelFees.value.length
    const checked = selected.value.length
    selectAllCheckbox.value.indeterminate = checked > 0 && checked < total
})

watch(
    () => props.babelfees,
    (newBabelFees) => {
        editableBabelFees.value = newBabelFees.data.map(a => ({
            id: a.id,
            babelfee_token: a.babelfee_token,
            fingerprint: a.fingerprint,
            rate: a.rate,
            logo_url: a.logo_url ?? 'https://www.wechselstuben.net/storage/logos/cardano-ada-logo.png'
        }))

        const availableKeys = newBabelFees.data.map(a => a.babelfee_token)
        selected.value = selected.value.filter(key => availableKeys.includes(key))
    },
    { immediate: true }
)

onMounted(() => {
    if (!selectAllCheckbox.value) return
    const total = sortedBabelFees.value.length
    const checked = selected.value.length
    selectAllCheckbox.value.indeterminate = checked > 0 && checked < total
})

const selectAllCheckbox = ref<HTMLInputElement | null>(null)

function submitForm() {
    form.get("/babelfees/create")
}

function updateBabelFee(babelfee: BabelFee) {
    router.get(`/babelfees/${babelfee.id}/edit`, {
        preserveScroll: true,
    });
}

const babelfeeToDelete = ref<BabelFee | null>(null);

function confirmBabelFee(babelfee: BabelFee) {
    babelfeeToDelete.value = babelfee;
}

function deleteBabelFeeConfirmed() {
    if (!babelfeeToDelete.value) return;

    const previousBabelFees = [...editableBabelFees.value];

    editableBabelFees.value = editableBabelFees.value.filter(
        (c) => c.id !== babelfeeToDelete.value!.id
    );

    router.delete(`/babelfees/${babelfeeToDelete.value.id}`, {
        preserveScroll: true,
        onError: () => {
            editableBabelFees.value = previousBabelFees;
        },
        onSuccess: () => {
            babelfeeToDelete.value = null;
        },
    });
}

</script>

<template>

    <Head :title="$t('babelfees')" />
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
                        {{ $t('accepted_babel_fees') }}
                    </h2>
                    <input v-model="searchQuery" id="search" type="text" :placeholder="$t('search...')"
                        class="ml-4 px-3 py-2 border rounded-lg text-sm w-64 dark:bg-gray-700 dark:text-gray-200" />
                </div>

                <div class="overflow-x-auto rounded-lg shadow">
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer"
                                    @click="sort('babelfee_token')" style="width: 20% !important">
                                    {{ $t('token') }}
                                </th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer"
                                    @click="sort('fingerprint')" style="width: 50% !important">
                                    {{ $t('fingerprint') }}
                                </th>
                                <th class="px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default"
                                    style="width: 19% !important">
                                    {{ $t('rate') }}
                                </th>
                                <th class="px-3 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default"
                                    style="width: 11% !important">
                                    {{ $t('actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="babelfee in filteredBabelFees" :key="babelfee.id"
                                class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800">
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200">
                                    <component :is="babelfee.fingerprint ? 'a' : 'div'"
                                        :href="babelfee.fingerprint ? 'https://cexplorer.io/asset/' + babelfee.fingerprint : null"
                                        target="_blank" rel="noopener noreferrer"
                                        class="flex items-center space-x-2 group transition-shadow duration-200 rounded">
                                        <img v-if="babelfee.logo_url" :src="babelfee.logo_url" alt="logo"
                                            class="w-6 h-6 rounded transition-transform duration-200"
                                            :class="{ 'group-hover:scale-105': babelfee.fingerprint }" />
                                        <span class="transition-colors duration-200 truncate"
                                            :class="{ 'group-hover:text-blue-600': babelfee.fingerprint }">
                                            <tt>{{ babelfee.babelfee_token }}</tt>
                                        </span>
                                    </component>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200 truncate max-w-xs">
                                    <component :is="babelfee.fingerprint ? 'a' : 'div'"
                                        :href="babelfee.fingerprint ? 'https://cexplorer.io/asset/' + babelfee.fingerprint : null"
                                        target="_blank" rel="noopener noreferrer"
                                        class="flex items-center space-x-2 group transition-shadow duration-200 rounded">
                                        <span class="transition-colors duration-200"
                                            :class="{ 'group-hover:text-blue-600': babelfee.fingerprint }">
                                            <tt>{{ babelfee.fingerprint }}</tt>
                                        </span>
                                    </component>
                                </td>
                                <td
                                    class="pr-12 py-2 text-sm text-right text-gray-900 dark:text-gray-200 truncate max-w-xs cursor-default">
                                    <tt>{{ Number(babelfee.rate).toLocaleString(undefined, {
                                        minimumFractionDigits: 3,
                                        maximumFractionDigits: 3
                                    }) }}</tt>
                                </td>
                                <td class="px-3 py-2 text-center flex items-center justify-center gap-2">

                                    <button type="button" @click="updateBabelFee(babelfee)"
                                        class="text-green-500 hover:text-green-700 dark:hover:text-green-400 p-1 cursor-pointer">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" />
                                            <path d="M21 3v5h-5" />
                                            <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16" />
                                            <path d="M8 16H3v5" />
                                        </svg>
                                    </button>
                                    <Dialog>
                                        <DialogTrigger as-child>
                                            <button type="button" @click="confirmBabelFee(babelfee)"
                                                class="text-red-500 hover:text-red-700 dark:hover:text-red-400 p-1 cursor-pointer">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                    fill="currentColor" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="1"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4m-4 0a1 1 0 00-1 1v1h6V4a1 1 0 00-1-1m-4 0h4" />
                                                </svg>
                                            </button>
                                        </DialogTrigger>
                                        <DialogContent v-if="babelfeeToDelete && babelfeeToDelete.id === babelfee.id">
                                            <DialogHeader>
                                                <DialogTitle>
                                                    {{ $t('delete_babelfee', { attribute: babelfee.babelfee_token }) }}
                                                </DialogTitle>
                                                <DialogDescription>
                                                    {{ $t('this_action_cannot_be_undone') }}
                                                </DialogDescription>
                                            </DialogHeader>
                                            <DialogFooter class="gap-8">
                                                <DialogClose as-child>
                                                    <Button variant="secondary">{{ $t('cancel') }}</Button>
                                                </DialogClose>
                                                <Button variant="destructive" @click="deleteBabelFeeConfirmed">
                                                    {{ $t('delete') }}
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>
                                </td>
                            </tr>
                            <tr v-if="!filteredBabelFees.length">
                                <td colspan="4" class="text-center py-4 text-gray-500">
                                    {{ $t('no_babelfees_found') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="filteredBabelFees.length > 0 && props.babelfees.links.length > 1"
                        class="mt-4 mb-4 flex justify-center space-x-1">
                        <button type="button" v-for="link in props.babelfees.links" :key="link.label"
                            :disabled="!link.url"
                            @click="link.url && router.get(link.url, {}, { preserveScroll: true })"
                            class="px-3 py-1 rounded border" :class="{
                                'bg-blue-600 text-white': link.active,
                                'bg-gray-200 text-gray-700': !link.active
                            }">
                            {{ $t(link.label) }}
                        </button>
                    </div>
                </div>
                <div class="h-5"></div>
                <div class="mt-4 flex justify-end">
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 dark:hover:bg-blue-500 disabled:opacity-50 cursor-pointer"
                        :disabled="form.processing">
                        {{ $t('add_babel_fee') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>