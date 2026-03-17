<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch, nextTick } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { submit } from '@/routes/assets';
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
    title: 'create_transaction',
    href: submit().url,
  },
];

const page = usePage()

interface Asset {
  destination: string | null
  token_number: number
  policy_id: string | null
  asset_name: string
  asset_hex: string
  fingerprint: string
  quantity: number
  decimals: number
  logo_url: string
}

interface Contact {
  id: number
  name?: string | null
  address?: string | null
}

const searchResults = ref<Contact[]>([])
const activeRow = ref<number | null>(null)
const loading = ref(false)

const searchCache = reactive<Record<string, Contact[]>>({})

let searchTimeout: number | undefined

async function searchContacts(query: string) {
  if (!query) {
    searchResults.value = []
    return
  }

  const prefix = Object.keys(searchCache).find(k => query.startsWith(k))

  const safeIncludes = (field: string | null | undefined, q: string) =>
    (field ?? '').toLowerCase().includes(q.toLowerCase())

  if (prefix) {
    const filtered = searchCache[prefix].filter(c =>
      safeIncludes(c.name, query) ||
      safeIncludes(c.address, query)
    )
    searchResults.value = filtered
    return
  }

  loading.value = true

  try {
    const res = await fetch(`/contacts/search?q=${encodeURIComponent(query)}`)
    if (res.ok) {
      const data: Contact[] = await res.json()
      searchResults.value = data
      searchCache[query] = data
    }
  } finally {
    loading.value = false
  }
}

function handleInput(query: string, rowIndex: number) {
  activeRow.value = rowIndex
  clearTimeout(searchTimeout)

  searchTimeout = window.setTimeout(() => {
    searchContacts(query)
  }, 300)
}

const props = defineProps<{
  assets: Asset[]
}>()

interface EditableAsset {
  id: string
  policy_id: string | null
  asset_name: string
  asset_hex: string
  fingerprint: string
  token_number: number
  decimals: number
  destination: string
  logo_url: string
  readonlyFromBackend?: boolean
}

const editableAssets = ref<EditableAsset[]>(props.assets.map((a) => ({
  id: crypto.randomUUID(),
  policy_id: a.policy_id,
  asset_name: a.asset_name,
  asset_hex: a.asset_hex,
  fingerprint: a.fingerprint,
  token_number: a.token_number ?? 0,
  decimals: a.decimals ?? 0,
  destination: a.destination ?? "",
  logo_url: a.logo_url ?? 'htts://www.wechselstuben.net/storage/logos/cardano-ada-logo.png',
  readonlyFromBackend: a.token_number > 0 && a.destination != null && a.destination.trim() !== ""
})))

const selected = ref<string[]>([])

const destinationRefs = ref<HTMLInputElement[]>([])

const form = useForm({
  selected_assets: [] as {
    policy_id: string | null
    asset_name: string
    asset_hex: string
    fingerprint: string
    token_number: number
    decimals: number
    destination: string
    logo_url: string
  }[],
})

const allSelected = computed({
  get: () =>
    editableAssets.value.length > 0 &&
    editableAssets.value.every(a => selected.value.includes(a.id)),
  set: (val: boolean) => {
    selected.value = val ? editableAssets.value.map(a => a.id) : []
  }
})

const isNumberInvalid = (asset: EditableAsset) =>
  selected.value.includes(asset.id) && asset.token_number <= 0

function isKnownContact(address: string | null | undefined) {
  if (!address || address.trim() === "") return false
  return Object.values(searchCache)
    .flat()
    .some(c => c.address === address)
}

const isDestinationInvalid = (asset: EditableAsset) =>
  selected.value.includes(asset.id) &&
  (asset.destination.trim() === "" || (!isKnownContact(asset.destination) && asset.token_number <= 0))

const isFormValid = computed(() => {
  return editableAssets.value
    .filter(a => selected.value.includes(a.id))
    .every(a => a.token_number > 0 && (isKnownContact(a.destination) || (a.destination !== null && a.destination.trim() !== "")))
})

function duplicateRow(index: number) {
  const original = editableAssets.value[index]
  if (!original) return

  const duplicate = {
    ...original,
    id: crypto.randomUUID(),
    token_number: 0,
    destination: "",
    readonlyFromBackend: false
  }

  editableAssets.value.splice(index + 1, 0, duplicate)
  nextTick(() => {
    destinationRefs.value[index + 1]?.focus()
  })
}

function deleteRow(index: number) {
  const asset = editableAssets.value[index]
  if (!asset) return

  // if (!confirm(`Delete asset "${asset.asset_name}"?`)) return

  editableAssets.value.splice(index, 1)

  nextTick(() => {
    const nextIndex = index < editableAssets.value.length ? index : editableAssets.value.length - 1
    destinationRefs.value[nextIndex]?.focus()
  })
}

const isReadonly = (index: number) => {
  const asset = editableAssets.value[index]
  return asset?.readonlyFromBackend ?? false
}

const selectAllCheckbox = ref<HTMLInputElement | null>(null)

watch(
  () => editableAssets.value.map(a => a.destination),
  () => {
    // triggers recompute of isFormValid
  },
  { deep: true }
)

watch(selected, () => {
  if (!selectAllCheckbox.value) return
  const total = editableAssets.value.length
  const checked = selected.value.length
  selectAllCheckbox.value.indeterminate =
    checked > 0 && checked < total
})

onMounted(() => {
  if (!selectAllCheckbox.value) return
  const total = editableAssets.value.length
  const checked = selected.value.length
  selectAllCheckbox.value.indeterminate =
    checked > 0 && checked < total
})

function submitForm() {
  form.selected_assets = editableAssets.value
    .filter(a => selected.value.includes(a.id) && (isKnownContact(a.destination) || (a.destination !== null && a.destination.trim() !== "")))
    .map(a => {
      const realValue =
        a.asset_name === "ADA"
          ? Math.round(a.token_number * 1e6)
          : Math.round(a.token_number * Math.pow(10, a.decimals))

      return {
        policy_id: a.policy_id,
        asset_name: a.asset_name,
        asset_hex: a.asset_hex,
        fingerprint: a.fingerprint,
        token_number: realValue,
        decimals: a.decimals,
        destination: a.destination,
        logo_url: a.logo_url
      }
    })

  if (form.selected_assets.length === 0) {
    return
  }

  form.post("/assets/transfer")
}

</script>

<template>

  <Head :title="$t('create_transaction')" />
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
            {{ $t('select_amount_and_recipients') }}
          </h2>
          <input class="px-3 py-2 border rounded-lg text-sm w-64" style="visibility: hidden;" />
        </div>

        <div class="overflow-x-auto rounded-lg shadow">
          <table
            class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg">
            <thead class="bg-gray-100 dark:bg-gray-800">
              <tr>
                <th class="px-4 py-2 text-center" style="width: 5% !important">
                  <input type="checkbox" id="checkbox" ref="selectAllCheckbox" v-model="allSelected" />
                </th>
                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default"
                  style="width: 15% !important">
                  {{ $t('token') }}
                </th>
                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default"
                  style="width: 20% !important">
                  {{ $t('number') }}
                </th>
                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default"
                  style="width: 49% !important">
                  {{ $t('recipient') }}
                </th>
                <th class="px-3 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default"
                  style="width: 11% !important">
                  {{ $t('actions') }}
                </th>
              </tr>
            </thead>

            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="(asset, index) in editableAssets" :key="asset.id"
                class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800">
                <td class="px-4 py-2 text-center">
                  <input type="checkbox" :value="asset.id" v-model="selected" :id="`checkbox-${index}`" />
                </td>
                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200">
                  <component :is="asset.fingerprint ? 'a' : 'div'"
                    :href="asset.fingerprint ? 'https://cexplorer.io/asset/' + asset.fingerprint : null" target="_blank"
                    rel="noopener noreferrer"
                    class="flex items-center space-x-2 group transition-shadow duration-200 rounded">
                    <img v-if="asset.logo_url" :src="asset.logo_url" alt="logo"
                      class="w-6 h-6 rounded transition-transform duration-200"
                      :class="{ 'group-hover:scale-105': asset.fingerprint }" />
                    <span class="transition-colors duration-200 truncate cursor-default"
                      :class="{ 'group-hover:text-blue-600': asset.fingerprint }">
                      <tt>{{ asset.asset_name }}</tt>
                    </span>
                  </component>
                </td>
                <td class="px-4 py-2 text-sm font-mono text-gray-900 dark:text-gray-200">
                  <input type="number" min="0" :step="asset.decimals > 0 ? (1 / Math.pow(10, asset.decimals)) : 1"
                    v-model.number="editableAssets[index].token_number" :id="`number-${index}`"
                    :disabled="!selected.includes(asset.id)" :class="[
                      'w-full px-1 font-mono rounded border bg-white dark:bg-gray-700',
                      'text-gray-900 dark:text-gray-200 border-gray-300 dark:border-gray-600',
                      'focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 dark:disabled:bg-gray-800 disabled:text-gray-400',
                      { 'border-red-500 focus:ring-red-500': isNumberInvalid(asset) }
                    ]" :readonly="isReadonly(index)" />
                </td>
                <td class="px-4 py-2 font-mono text-gray-900 dark:text-gray-200">
                  <input type="text" :placeholder="$t('type_to_search_contact')"
                    v-model="editableAssets[index].destination" :id="`destination-${index}`"
                    @input="handleInput(editableAssets[index].destination, index)"
                    :disabled="!selected.includes(asset.id)" :class="[
                      'w-full px-1 text-sm font-mono rounded border bg-white dark:bg-gray-700',
                      'text-gray-900 dark:text-gray-200 border-gray-300 dark:border-gray-600',
                      'focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 dark:disabled:bg-gray-800 disabled:text-gray-400',
                      { 'border-red-500 focus:ring-red-500': isDestinationInvalid(asset) }
                    ]" :readonly="isReadonly(index)" />
                  <ul v-if="activeRow === index && searchResults.length > 0"
                    class="absolute z-50 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg mt-1 w-full max-w-[350px] max-h-40 overflow-y-auto shadow-lg">
                    <li v-for="c in searchResults" :key="c.id"
                      @click="editableAssets[index].destination = c.address ?? ''; activeRow = null"
                      class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer font-mono w-full max-w-full overflow-hidden truncate">
                      {{ c.name }} ({{ c.address }})
                    </li>
                  </ul>
                  <!-- Loading indicator -->
                  <div v-if="activeRow === index && loading" class="absolute right-2 top-2">
                    <svg class="w-4 h-4 animate-spin text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                      viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                  </div>
                </td>
                <td class="px-3 py-2 text-center flex items-center justify-center gap-2">
                  <!-- Duplicate row -->
                  <button type="button" @click="duplicateRow(index)"
                    class="text-green-500 hover:text-green-700 dark:hover:text-green-400 p-1 cursor-pointer">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                      stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M12 4v16m8-8H4" />
                    </svg>
                  </button>

                  <!-- Delete row -->
                  <button type="button" @click="deleteRow(index)"
                    class="text-red-500 hover:text-red-700 dark:hover:text-red-400 p-1 cursor-pointer">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"
                      stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4m-4 0a1 1 0 00-1 1v1h6V4a1 1 0 00-1-1m-4 0h4" />
                    </svg>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="h-5"></div>
        <div class="mt-4 flex justify-end">
          <button type="submit"
            class="px-4 py-2 rounded-lg shadow cursor-pointer text-white bg-blue-600 hover:bg-blue-700 dark:hover:bg-blue-500 disabled:bg-gray-400 disabled:cursor-not-allowed disabled:opacity-70"
            :disabled="form.processing || selected.length === 0 || !isFormValid">
            {{ $t('select_payment') }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
