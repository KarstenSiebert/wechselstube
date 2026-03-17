<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { index } from '@/routes/payments';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { Dialog, DialogTrigger, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter, DialogClose } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import FlashMessage from "@/components/FlashMessage.vue";
import debounce from "lodash/debounce";
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
        title: "requests_minutes",
        href: index().url,
    },
];

interface Payment {
    id: number
    name: string
    address: string
    policy_id: string | null
    hash: string | null
    asset_name: string
    asset_hex: string | null
    fingerprint: string
    quantity: number
    direction: string
    decimals: number
    logo_url?: string | null
    status: string
    updated_at: string
    canUpdate: boolean
    canDelete: boolean
    canPay: boolean
    canDeny: boolean
}

const props = defineProps<{
    payments: {
        data: Payment[]
        links: { url: string | null, label: string, active: boolean }[]
        meta: { current_page: number, last_page: number, per_page: number, total: number }
    }
}>()

let intervalId: ReturnType<typeof setInterval> | null = null

const sortField = ref<keyof Payment>("updated_at")
const sortAsc = ref(false)

const page = usePage()

const pagesToShow = computed<(number | string)[]>(() => {
    const total = props.payments.meta.last_page
    const current = props.payments.meta.current_page
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

function goTo(page: number) {
    router.get(
        '/payments',
        {
            page,
            search: searchQuery.value || undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        }
    )
}

const selected = ref<number[]>([])

const allSelected = computed({
    get: () => {
        const incoming = sortedPayments.value.filter(p => p.direction === 'incoming' && p.status !== 'paid');
        return incoming.length > 0 && incoming.every(p => selected.value.includes(p.id));
    },
    set: (val: boolean) => {
        const incoming = sortedPayments.value.filter(p => p.direction === 'incoming' && p.status !== 'paid');
        if (val) {
            selected.value = incoming.map(p => p.id);
        } else {
            selected.value = [];
        }
    }
});

const selectAllCheckbox = ref<HTMLInputElement | null>(null)

const form = useForm({
    selected_payment: [] as Payment[],
})

const editablePayments = ref<Payment[]>(
    props.payments.data.map((a) => ({
        ...a,
        logo_url: a.logo_url ?? 'https://www.wechselstuben.net/storage/logos/wechselstuben-logo.png',
    }))
)

intervalId = setInterval(() => {
    router.reload({
        only: ["payments"],
        data: { search: searchQuery.value || undefined },
    });
}, 60000)

function sort(field: keyof Payment) {
    if (sortField.value === field) {
        sortAsc.value = !sortAsc.value
    } else {
        sortField.value = field
        sortAsc.value = true
    }
}

const sortedPayments = computed(() => {
    return [...editablePayments.value].sort((a, b) => {
        let valA = a[sortField.value] ?? ""
        let valB = b[sortField.value] ?? ""

        if (sortField.value === "updated_at") {
            valA = new Date(a.updated_at).getTime()
            valB = new Date(b.updated_at).getTime()
        }

        if (valA < valB) return sortAsc.value ? -1 : 1
        if (valA > valB) return sortAsc.value ? 1 : -1
        return 0
    })
})

watch(selected, () => {
    if (!selectAllCheckbox.value) return;

    const incoming = sortedPayments.value.filter(p => p.direction === 'incoming' && p.status !== 'paid');
    const total = incoming.length;
    const checked = selected.value.length;

    selectAllCheckbox.value.indeterminate = checked > 0 && checked < total;
});

watch(
    () => props.payments,
    (newPayments) => {
        editablePayments.value = newPayments.data.map((p) => ({
            ...p,
            logo_url: p.logo_url ?? 'https://www.wechselstuben.net/storage/logos/wechselstuben-logo.png',
        }));

        const availableIds = newPayments.data.map(p => p.id);
        selected.value = selected.value.filter(id => availableIds.includes(id));
    },
    { immediate: true }
);

onMounted(() => {
    if (!selectAllCheckbox.value) return;

    const incoming = sortedPayments.value.filter(p => p.direction === 'incoming' && p.status !== 'paid');
    const total = incoming.length;
    const checked = selected.value.length;

    selectAllCheckbox.value.indeterminate = checked > 0 && checked < total;
});

const urlParams = new URLSearchParams(window.location.search)
const searchQuery = ref(urlParams.get("search") || "")

const triggerSearch = debounce(() => {
    const query = searchQuery.value.trim();

    router.get(
        "/payments",
        query ? { page: 1, search: query } : { page: 1 },
        {
            preserveScroll: true,
            replace: true,
            preserveState: false,
        }
    );
}, 500);

watch(searchQuery, () => {
    triggerSearch();
});

watch(
    () => window.location.search,
    () => {
        const params = new URLSearchParams(window.location.search);
        const newSearch = params.get("search") || "";
        if (newSearch !== searchQuery.value) {
            searchQuery.value = newSearch;
        }
    }
);

function submitForm() {
    form.get("/payments/create")
}

function updatePayment(payment: Payment) {
    router.get(`/payments/${payment.id}/edit`, { preserveScroll: true });
}

function paySelectedPayments() {
    if (selected.value.length === 0) return;

    const selectedPayments = editablePayments.value
        .filter(p => selected.value.includes(p.id))
        .map(p => ({
            asset_name: p.asset_name,
            address: p.address,
            asset_hex: p.asset_hex,
            policy_id: p.policy_id,
            quantity: p.quantity,
            decimals: p.decimals,
            fingerprint: p.fingerprint,
            logo_url: p.logo_url,
        }));

    router.post(
        "/assets/submit",
        { selected_assets: selectedPayments },
        { preserveScroll: true }
    );
}

const paymentToDelete = ref<Payment | null>(null);

function confirmDelete(payment: Payment) {
    paymentToDelete.value = payment;
}

function deletePaymentConfirmed() {
    if (!paymentToDelete.value) return;

    const previousPayments = [...editablePayments.value];

    editablePayments.value = editablePayments.value.filter(
        (c) => c.id !== paymentToDelete.value!.id
    );

    router.delete(`/payments/${paymentToDelete.value.id}`, {
        preserveScroll: true,
        onError: () => {
            editablePayments.value = previousPayments;
        },
        onSuccess: () => {
            paymentToDelete.value = null; // close dialog
        },
    });
}

onUnmounted(() => {
    if (intervalId) clearInterval(intervalId);
})

</script>

<template>

    <Head :title="$t('requests')" />
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
                        {{ $t('payment_requests') }}
                    </h2>
                    <input v-model="searchQuery" id="search" type="text" :placeholder="$t('search...')"
                        class="ml-4 px-3 py-2 border rounded-lg text-sm w-64 dark:bg-gray-700 dark:text-gray-200" />
                </div>

                <div class="overflow-x-auto rounded-lg shadow">
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 text-center" style="width: 5% !important">
                                    <template
                                        v-if="sortedPayments.some(p => p.direction === 'incoming' && p.status !== 'paid')">
                                        <input type="checkbox" id="checkbox" ref="selectAllCheckbox"
                                            v-model="allSelected" />
                                    </template>
                                </th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer"
                                    @click="sort('name')" style="width: 15% !important">
                                    {{ $t('name') }}
                                </th>

                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer"
                                    @click="sort('asset_name')" style="width: 15% !important">
                                    {{ $t('token') }}
                                </th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer"
                                    @click="sort('quantity')" style="width: 20% !important">
                                    {{ $t('number') }}
                                </th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer"
                                    @click="sort('fingerprint')" style="width: 28% !important">
                                    {{ $t('fingerprint') }}
                                </th>
                                <th class="px-3 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default"
                                    style="width: 11% !important">
                                    {{ $t('actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="(payment, index) in sortedPayments" :key="payment.id"
                                class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800">
                                <td class="px-4 py-2 text-center">
                                    <!-- Show checkbox only for incoming & not paid -->
                                    <template v-if="payment.direction === 'incoming' && payment.status !== 'paid'">
                                        <input type="checkbox" :value="payment.id" :id="`payment-${index}`"
                                            v-model="selected" />
                                    </template>

                                    <!-- Show arrows if paid -->
                                    <template v-else-if="payment.status === 'paid'">
                                        <svg v-if="payment.direction === 'incoming'" xmlns="http://www.w3.org/2000/svg"
                                            class="w-4 h-4 text-green-500 mx-auto" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor" stroke-width="3">
                                            <!-- Arrow down -->
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 4v16m0 0l-6-6m6 6l6-6" />
                                        </svg>

                                        <svg v-else-if="payment.direction === 'outgoing'"
                                            xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-red-500 mx-auto"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <!-- Arrow up -->
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 20V4m0 0l6 6m-6-6l-6 6" />
                                        </svg>
                                    </template>
                                </td>
                                <td
                                    class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200 truncate cursor-default max-w-xs overflow-hidden text-ellipsis">
                                    <span class="max-w-xs overflow-hidden truncate text-ellipsis"><tt>{{ payment.name
                                            }}</tt></span>
                                </td>
                                <td
                                    class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200 truncate max-w-xs cursor-default">
                                    <component :is="payment.fingerprint ? 'a' : 'div'"
                                        :href="payment.fingerprint ? 'https://cexplorer.io/asset/' + payment.fingerprint : null"
                                        target="_blank" rel="noopener noreferrer"
                                        class="flex items-center space-x-2 group transition-shadow duration-200 rounded">
                                        <img v-if="payment.logo_url" :src="payment.logo_url" alt="logo"
                                            class="w-6 h-6 rounded transition-transform duration-200"
                                            :class="{ 'group-hover:scale-105': payment.fingerprint }" />
                                        <span
                                            class="transition-colors duration-200 truncate cursor-pointer max-w-xs overflow-hidden text-ellipsis"
                                            :class="{ 'group-hover:text-blue-600': payment.fingerprint }">
                                            <tt>{{ payment.asset_name }}</tt>
                                        </span>
                                    </component>
                                </td>
                                <td
                                    class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-200 cursor-default max-w-xs overflow-hidden">
                                    <tt>{{
                                        (payment.asset_name === "ADA"
                                            ? payment.quantity / 1e6
                                            : payment.quantity / Math.pow(10, payment.decimals)
                                        ).toLocaleString(undefined, {
                                            minimumFractionDigits: payment.decimals > 6 ? 6 : payment.decimals,
                                            maximumFractionDigits: payment.decimals > 6 ? 6 : payment.decimals
                                        })
                                    }}</tt>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200">
                                    <component :is="payment.fingerprint ? 'a' : 'div'"
                                        :href="payment.fingerprint ? 'https://cexplorer.io/asset/' + payment.fingerprint : null"
                                        target="_blank" rel="noopener noreferrer"
                                        class="flex items-center space-x-2 group transition-shadow duration-200 rounded">
                                        <span
                                            class="transition-colors truncate duration-200 overflow-hidden text-ellipsis"
                                            :class="{ 'group-hover:text-blue-600': payment.fingerprint }">
                                            <tt>{{ payment.fingerprint.length > 28 ? payment.fingerprint.slice(0, 26) +
                                                '…' : payment.fingerprint }}</tt>
                                        </span>
                                    </component>
                                </td>

                                <td class="px-3 py-2 text-center flex items-center justify-center gap-2">
                                    <template v-if="payment.status === 'paid'">
                                        <a :href="`https://www.cexplorer.io/tx/${payment.hash}`" target="_blank"
                                            rel="noopener noreferrer">
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M5 13l4 4L19 7" />
                                                </svg>
                                                {{ $t('paid') }}
                                            </span>
                                        </a>
                                    </template>

                                    <!-- Outgoing actions -->
                                    <template v-if="payment.direction === 'outgoing'">
                                        <button type="button" v-if="payment.canUpdate" @click="updatePayment(payment)"
                                            class="text-green-500 hover:text-green-700 dark:hover:text-green-400 p-1 cursor-pointer">
                                            <!-- update icon -->
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
                                                <button type="button" v-if="payment.canDelete"
                                                    @click="confirmDelete(payment)"
                                                    class="text-red-500 hover:text-red-700 dark:hover:text-red-400 p-1 cursor-pointer">
                                                    <!-- delete icon -->
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                        fill="currentColor" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="1"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4m-4 0a1 1 0 00-1 1v1h6V4a1 1 0 00-1-1m-4 0h4" />
                                                    </svg>
                                                </button>
                                            </DialogTrigger>
                                            <DialogContent v-if="paymentToDelete && paymentToDelete.id === payment.id">
                                                <DialogHeader>
                                                    <DialogTitle>
                                                        {{ $t('delete_payment_request', { attribute: payment.name }) }}
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        {{ $t('this_action_cannot_be_undone') }}
                                                    </DialogDescription>
                                                </DialogHeader>
                                                <DialogFooter class="gap-8">
                                                    <DialogClose as-child>
                                                        <Button variant="secondary">{{ $t('cancel') }}</Button>
                                                    </DialogClose>
                                                    <Button variant="destructive" @click="deletePaymentConfirmed">
                                                        {{ $t('delete') }}
                                                    </Button>
                                                </DialogFooter>
                                            </DialogContent>
                                        </Dialog>
                                    </template>

                                    <!-- Incoming actions -->

                                </td>
                            </tr>
                            <tr v-if="!sortedPayments.length">
                                <td colspan="5" class="text-center py-4 text-gray-500">
                                    {{ $t('no_requests_found') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="sortedPayments.length > 0 && props.payments.meta.last_page > 1"
                        class="flex justify-center mt-4 mb-4 space-x-1">
                        <button type="button" v-if="props.payments.meta.current_page > 1"
                            @click="goTo(props.payments.meta.current_page - 1)"
                            class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300">{{ $t('prev') }}</button>
                        <template v-for="page in pagesToShow" :key="page">
                            <span v-if="page === '...'" class="px-3 py-1 text-gray-500 select-none">...</span>
                            <button type="button" v-else @click="goTo(Number(page))" class="px-3 py-1 border rounded"
                                :class="page === props.payments.meta.current_page ? 'bg-blue-500 text-white' : 'bg-gray-200 hover:bg-gray-300'">
                                {{ page }}
                            </button>
                        </template>
                        <button type="button" v-if="props.payments.meta.current_page < props.payments.meta.last_page"
                            @click="goTo(props.payments.meta.current_page + 1)"
                            class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300">{{
                                $t('next') }}</button>
                    </div>
                </div>
                <div class="h-5"></div>
                <div class="mt-4 flex justify-end gap-8">
                    <button type="button" @click="paySelectedPayments"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 dark:hover:bg-blue-500 disabled:opacity-50 cursor-pointer"
                        :disabled="selected.length === 0">
                        {{ $t('pay_selected') }}
                    </button>

                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 dark:hover:bg-blue-500 disabled:opacity-50 cursor-pointer"
                        :disabled="form.processing">
                        {{ $t('request_payment') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>