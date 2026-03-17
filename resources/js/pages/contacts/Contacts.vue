<script setup lang="ts">
import { ref, computed, watch } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { index } from '@/routes/contacts';
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
        title: "contacts",
        href: index().url,
    },
];

interface Contact {
    id: number,
    name: string,
    address: string
}

const props = defineProps<{
    contacts: {
        data: Contact[]
        links: { url: string | null, label: string, active: boolean }[]
        meta: { current_page: number, last_page: number, per_page: number, total: number }
    }
}>()

const page = usePage()

const form = useForm({
    selected_contacts: [] as Contact[],
})

const editableContacts = ref<Contact[]>(
    props.contacts.data.map((a) => ({
        ...a
    }))
)

const sortField = ref<keyof Contact>("name")
const sortAsc = ref(true)

function sort(field: keyof Contact) {
    if (sortField.value === field) {
        sortAsc.value = !sortAsc.value
    } else {
        sortField.value = field
        sortAsc.value = true
    }
}

const sortedContacts = computed(() => {
    return [...editableContacts.value].sort((a, b) => {
        const valA = a[sortField.value] ?? (sortField.value === "name" ? 0 : "")
        const valB = b[sortField.value] ?? (sortField.value === "name" ? 0 : "")

        if (valA < valB) return sortAsc.value ? -1 : 1
        if (valA > valB) return sortAsc.value ? 1 : -1
        return 0
    })
})

const searchQuery = ref("")

const filteredContacts = computed(() => {
    if (!searchQuery.value) return sortedContacts.value

    const query = searchQuery.value.toLowerCase()

    return sortedContacts.value.filter(p =>
        p.name.toLowerCase().includes(query) ||
        p.address.toLowerCase().includes(query)
    )
})

watch(
    () => props.contacts,
    (newContacts) => {
        editableContacts.value = newContacts.data.map(a => ({
            id: a.id,
            name: a.name,
            address: a.address
        }))
    },
    { immediate: true }
)

function submitForm() {
    form.get("/contacts/create")
}

function updateContact(contact: Contact) {
    router.get(`/contacts/${contact.id}/edit`, {
        preserveScroll: true,
    });
}

const contactToDelete = ref<Contact | null>(null);

function confirmDelete(contact: Contact) {
    contactToDelete.value = contact;
}

function deleteContactConfirmed() {
    if (!contactToDelete.value) return;

    const previousContacts = [...editableContacts.value];

    editableContacts.value = editableContacts.value.filter(
        (c) => c.id !== contactToDelete.value!.id
    );

    router.delete(`/contacts/${contactToDelete.value.id}`, {
        preserveScroll: true,
        onError: () => {
            editableContacts.value = previousContacts;
        },
        onSuccess: () => {
            contactToDelete.value = null; // close dialog
        },
    });
}

</script>

<template>

    <Head :title="$t('contacts')" />
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
                        {{ $t('contact_list') }}
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
                                    @click="sort('name')" style="width: 15% !important">
                                    {{ $t('name') }}
                                </th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer"
                                    @click="sort('address')" style="width: 72% !important">
                                    {{ $t('wallet_address') }}
                                </th>
                                <th class="px-3 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default"
                                    style="width: 11% !important">
                                    {{ $t('actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="contact in filteredContacts" :key="contact.id"
                                class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800">
                                <td
                                    class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200 truncate max-w-xs overflow-hidden text-ellipsis cursor-default">
                                    <tt>{{ contact.name }}</tt>
                                </td>
                                <td
                                    class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200 truncate max-w-xs overflow-hidden text-ellipsis cursor-default">
                                    <tt>{{ contact.address }}</tt>
                                </td>
                                <td class="px-3 py-2 text-center flex items-center justify-center gap-2">
                                    <button type="button" @click="updateContact(contact)"
                                        class="text-green-500 hover:text-green-700 dark:hover:text-green-400 p-1 cursor-pointer">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" />
                                            <path d="M21 3v5h-5" />
                                            <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16" />
                                            <path d="M8 16H3v5" />
                                        </svg>
                                    </button>
                                    <!-- Delete button triggers the dialog -->
                                    <Dialog>
                                        <DialogTrigger as-child>
                                            <button type="button" @click="confirmDelete(contact)"
                                                class="text-red-500 hover:text-red-700 dark:hover:text-red-400 p-1 cursor-pointer">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                    fill="currentColor" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="1"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4m-4 0a1 1 0 00-1 1v1h6V4a1 1 0 00-1-1m-4 0h4" />
                                                </svg>
                                            </button>
                                        </DialogTrigger>
                                        <DialogContent v-if="contactToDelete && contactToDelete.id === contact.id">
                                            <DialogHeader>
                                                <DialogTitle>
                                                    {{ $t('delete_contact', { attribute: contact.name }) }}
                                                </DialogTitle>
                                                <DialogDescription>
                                                    {{ $t('this_action_cannot_be_undone') }}
                                                </DialogDescription>
                                            </DialogHeader>
                                            <DialogFooter class="gap-8">
                                                <DialogClose as-child>
                                                    <Button variant="secondary">{{ $t('cancel') }}</Button>
                                                </DialogClose>
                                                <Button variant="destructive" @click="deleteContactConfirmed">
                                                    {{ $t('delete') }}
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>
                                </td>
                            </tr>
                            <tr v-if="!filteredContacts.length">
                                <td colspan="4" class="text-center py-4 text-gray-500">
                                    {{ $t('no_contacts_found') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="filteredContacts.length > 0 && props.contacts.links.length > 1"
                        class="mt-4 mb-4 flex justify-center gap-2">
                        <button type="button" v-for="link in props.contacts.links" :key="link.label"
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
                        {{ $t('create_contact') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>