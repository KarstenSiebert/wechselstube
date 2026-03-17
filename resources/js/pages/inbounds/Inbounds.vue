<script setup lang="ts">
import { ref, computed, onMounted, watch } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { index } from '@/routes/inbounds';
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
        title: 'inbounds',
        href: index().url,
    },
];

interface Inbound {
    id: number,
    inbound_token: string,
    fingerprint: string,
    location: string,
    decimals: number,
    cost: number,
    hash: string,
    logo_url?: string
}

const props = defineProps<{
    inbounds: {
        data: Inbound[]
        links: { url: string | null, label: string, active: boolean }[]
        meta: { current_page: number, last_page: number, per_page: number, total: number }
    }
}>()

const page = usePage()

const form = useForm({
    selected_inbounds: [] as Inbound[],
})

const editableInbounds = ref<Inbound[]>(
    props.inbounds.data.map((a) => ({
        ...a,
        id: a.id,
        inbound_token: a.inbound_token,
        fingerprint: a.fingerprint,
        location: a.location,
        decimals: a.decimals,
        cost: a.cost,
        hash: a.hash,
        logo_url: a.logo_url ?? 'https://www.wechselstuben.net/storage/logos/wechselstuben-logo.png'
    }))
)

const sortField = ref<keyof Inbound>("inbound_token")
const sortAsc = ref(true)

const selected = ref<string[]>([])

async function fetchQRCodeBase64(url: string): Promise<string> {
    const response = await fetch(url)
    if (!response.ok) throw new Error('Failed to fetch QR code')
    const base64String = await response.text()
    return base64String
}

const qrDialogOpen = ref(false)
const qrBase64 = ref<string | null>(null)
const qrHash = ref<string | null>(null)

async function openQRCodeDialog(hash: string) {
    try {
        const url = `https://www.wechselstuben.net/inbounds/qrcode?hash=${encodeURIComponent(hash)}`

        const base64 = await fetchQRCodeBase64(url)

        qrBase64.value = base64
        qrHash.value = hash
        qrDialogOpen.value = true

    } catch (error) {
        console.error('Failed to fetch QR code:', error)
    }
}

function sort(field: keyof Inbound) {
    if (sortField.value === field) {
        sortAsc.value = !sortAsc.value
    } else {
        sortField.value = field
        sortAsc.value = true
    }
}

const sortedInbounds = computed(() => {
    return [...editableInbounds.value].sort((a, b) => {
        const valA = a[sortField.value] ?? (sortField.value === "inbound_token" ? 0 : "")
        const valB = b[sortField.value] ?? (sortField.value === "inbound_token" ? 0 : "")

        if (valA < valB) return sortAsc.value ? -1 : 1
        if (valA > valB) return sortAsc.value ? 1 : -1
        return 0
    })
})

const searchQuery = ref("")

const filteredInbounds = computed(() => {
    if (!searchQuery.value) return sortedInbounds.value

    const query = searchQuery.value.toLowerCase()

    return sortedInbounds.value.filter(p =>
        p.inbound_token.toLowerCase().includes(query) ||
        p.fingerprint.toLowerCase().includes(query) ||
        p.location.toLowerCase().includes(query) ||
        String(p.cost).toLowerCase().includes(query)
    )
})

watch(selected, () => {
    if (!selectAllCheckbox.value) return
    const total = sortedInbounds.value.length
    const checked = selected.value.length
    selectAllCheckbox.value.indeterminate = checked > 0 && checked < total
})

watch(
    () => props.inbounds,
    (newInbounds) => {
        editableInbounds.value = newInbounds.data.map(a => ({
            id: a.id,
            inbound_token: a.inbound_token,
            fingerprint: a.fingerprint,
            location: a.location,
            decimals: a.decimals,
            cost: a.cost,
            hash: a.hash,
            logo_url: a.logo_url ?? 'https://www.wechselstuben.net/storage/logos/cardano-ada-logo.png'
        }))

        const availableKeys = newInbounds.data.map(a => a.inbound_token)
        selected.value = selected.value.filter(key => availableKeys.includes(key))
    },
    { immediate: true }
)

onMounted(() => {
    if (!selectAllCheckbox.value) return
    const total = sortedInbounds.value.length
    const checked = selected.value.length
    selectAllCheckbox.value.indeterminate = checked > 0 && checked < total
})

const selectAllCheckbox = ref<HTMLInputElement | null>(null)

function submitForm() {
    form.get("/inbounds/create")
}

function updateInbound(inbound: Inbound) {
    router.get(`/inbounds/${inbound.id}/edit`, {
        preserveScroll: true,
    });
}

const inboundToDelete = ref<Inbound | null>(null);

function confirmInbound(inbound: Inbound) {
    inboundToDelete.value = inbound;
}

function deleteInboundConfirmed() {
    if (!inboundToDelete.value) return;

    const previousInbounds = [...editableInbounds.value];

    editableInbounds.value = editableInbounds.value.filter(
        (c) => c.id !== inboundToDelete.value!.id
    );

    router.delete(`/inbounds/${inboundToDelete.value.id}`, {
        preserveScroll: true,
        onError: () => {
            editableInbounds.value = previousInbounds;
        },
        onSuccess: () => {
            inboundToDelete.value = null;
        },
    });
}

</script>

<template>

    <Head :title="$t('inbounds')" />
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
                        {{ $t('inbounds') }}
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
                                    @click="sort('inbound_token')" style="width: 20% !important">
                                    {{ $t('token') }}
                                </th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer"
                                    @click="sort('fingerprint')" style="width: 50% !important">
                                    {{ $t('location') }}
                                </th>
                                <th class="px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default"
                                    style="width: 19% !important">
                                    {{ $t('cost') }}
                                </th>
                                <th class="px-3 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default"
                                    style="width: 11% !important">
                                    {{ $t('actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="inbound in filteredInbounds" :key="inbound.id"
                                class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800">
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200">
                                    <component :is="inbound.fingerprint ? 'a' : 'div'"
                                        :href="inbound.fingerprint ? 'https://cexplorer.io/asset/' + inbound.fingerprint : null"
                                        target="_blank" rel="noopener noreferrer"
                                        class="flex items-center space-x-2 group transition-shadow duration-200 rounded">
                                        <img v-if="inbound.logo_url" :src="inbound.logo_url" alt="logo"
                                            class="w-6 h-6 rounded transition-transform duration-200"
                                            :class="{ 'group-hover:scale-105': inbound.fingerprint }" />
                                        <span class="transition-colors duration-200 truncate"
                                            :class="{ 'group-hover:text-blue-600': inbound.fingerprint }">
                                            <tt>{{ inbound.inbound_token }}</tt>
                                        </span>
                                    </component>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200 truncate max-w-xs overflow-hidden text-ellipsis cursor-pointer"
                                    @click="openQRCodeDialog(inbound.hash)" :title="$t('click_to_open_QR_code')">
                                    <span class="text-blue-600"><tt>{{
                                        inbound.location }}</tt>
                                    </span>
                                </td>
                                <td
                                    class="pr-12 py-2 text-sm text-right text-gray-900 dark:text-gray-200 truncate max-w-xs cursor-default">
                                    <tt>{{
                                        (inbound.inbound_token === "ADA"
                                            ? inbound.cost / 1e6
                                            : inbound.cost / Math.pow(10, inbound.decimals)
                                        ).toLocaleString(undefined, {
                                            minimumFractionDigits: inbound.decimals > 6 ? 6 : inbound.decimals,
                                            maximumFractionDigits: inbound.decimals > 6 ? 6 : inbound.decimals
                                        })


                                    }}</tt>
                                </td>
                                <td class="px-3 py-2 text-center flex items-center justify-center gap-2">

                                    <button type="button" @click="updateInbound(inbound)"
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
                                            <button type="button" @click="confirmInbound(inbound)"
                                                class="text-red-500 hover:text-red-700 dark:hover:text-red-400 p-1 cursor-pointer">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                    fill="currentColor" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="1"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4m-4 0a1 1 0 00-1 1v1h6V4a1 1 0 00-1-1m-4 0h4" />
                                                </svg>
                                            </button>
                                        </DialogTrigger>
                                        <DialogContent v-if="inboundToDelete && inboundToDelete.id === inbound.id">
                                            <DialogHeader>
                                                <DialogTitle>
                                                    {{ $t('delete_inbound', { attribute: inbound.location }) }}
                                                </DialogTitle>
                                                <DialogDescription>
                                                    {{ $t('this_action_cannot_be_undone') }}
                                                </DialogDescription>
                                            </DialogHeader>
                                            <DialogFooter class="gap-8">
                                                <DialogClose as-child>
                                                    <Button variant="secondary">{{ $t('cancel') }}</Button>
                                                </DialogClose>
                                                <Button variant="destructive" @click="deleteInboundConfirmed">
                                                    {{ $t('delete') }}
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>
                                </td>
                            </tr>
                            <tr v-if="!filteredInbounds.length">
                                <td colspan="4" class="text-center py-4 text-gray-500">
                                    {{ $t('no_inbounds_found') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="filteredInbounds.length > 0 && props.inbounds.links.length > 1"
                        class="mt-4 mb-4 flex justify-center space-x-1">
                        <button type="button" v-for="link in props.inbounds.links" :key="link.label"
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
                        {{ $t('add_inbound') }}
                    </button>
                </div>
            </form>
        </div>

        <Dialog v-model:open="qrDialogOpen">
            <DialogContent class="max-w-sm flex flex-col items-center justify-center">
                <DialogHeader>
                    <DialogTitle>{{ $t('qr_code') }}</DialogTitle>
                    <DialogDescription>{{ $t('scan_this_QR_code_to_view_details') }}</DialogDescription>
                </DialogHeader>

                <div v-if="qrBase64" class="flex justify-center items-center p-4">
                    <img :src="qrBase64" alt="{{ $t('qr_code') }}" class="max-w-xs rounded-lg shadow-lg" />
                </div>
                <div>
                    <small class="text-gray-500">{{ qrHash }}</small>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>