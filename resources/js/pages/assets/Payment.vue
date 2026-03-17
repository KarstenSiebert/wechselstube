<script setup lang="ts">
import { ref, onMounted } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { payment } from '@/routes/assets';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import FlashMessage from "@/components/FlashMessage.vue";
import Quill from 'quill';
import ImageCompress from 'quill-image-compress';

const breadcrumbs: BreadcrumbItem[] = [
    { title: "select_payment", href: payment().url },
];

const page = usePage();

const props = defineProps<{
    selected_assets: Asset[],
    available_assets: Asset[]
}>();

const selected = ref<string | null>(null);

function getKey(asset: Asset) {
    return asset.provider_name ? `${asset.asset_name}|${asset.provider_name}` : asset.asset_name;
}

const quilllimit = 2048;
const editortype = false;

const quillContainer = ref<HTMLDivElement | null>(null);
const quillArea = ref<HTMLTextAreaElement | null>(null);
const editor = ref<Quill | null>(null);

interface Asset {
    destination: string | null;
    token_number: number;
    policy_id: string | null;
    asset_name: string;
    asset_hex: string;
    fingerprint: string;
    quantity: number;
    logo_url: string;
    provider_name: string | null;
    address: string | null;
    decimals: number | null;
    rate: number | null;
}

interface ChosenAsset {
    asset_name: string;
    asset_hex: string;
    policy_id: string | null;
    provider_name: string | null;
    address: string | null;
    decimals: number | null;
    rate: number | null;
}

const form = useForm<{
    selected_assets: Asset[],
    chosen_asset: ChosenAsset,
    additional_info: string
}>({
    selected_assets: props.selected_assets,
    chosen_asset: {
        asset_name: '',
        asset_hex: '',
        policy_id: null,
        provider_name: null,
        address: null,
        decimals: null,
        rate: null
    },
    additional_info: ''
});

const formatRate = (rate: number | null) => {
    return rate !== null
        ? Number(rate).toLocaleString(undefined, { minimumFractionDigits: 3, maximumFractionDigits: 3 })
        : ' ';
};

function submitForm() {
    if (!selected.value) return;

    const [assetName, providerName] = selected.value.split('|');

    const asset = props.available_assets.find(a =>
        a.asset_name === assetName && a.provider_name === (providerName ?? a.provider_name)
    );
    if (!asset) return;

    form.chosen_asset = {
        asset_name: asset.asset_name,
        asset_hex: asset.asset_hex,
        policy_id: asset.policy_id ?? null,
        provider_name: asset.provider_name ?? null,
        address: asset.address ?? null,
        decimals: asset.decimals ?? null,
        rate: asset.rate ?? null
    };

    form.post("/assets/payment");
}

onMounted(() => {
    if (!quillContainer.value || !quillArea.value) return;

    Quill.register('modules/imageCompress', ImageCompress);

    const toolbarOptions = editortype
        ? []
        : [['image']];

    editor.value = new Quill(quillContainer.value, {
        theme: 'snow',
        modules: {
            toolbar: toolbarOptions,
            imageCompress: {
                quality: 0.9,
                maxWidth: 192,
                maxHeight: 192,
                imageType: 'image/webp',
                debug: false,
                suppressErrorLogging: true,
                handleOnPaste: true,
            }
        }
    });

    editor.value.root.innerHTML = quillArea.value.value || '';

    editor.value.on('text-change', () => {
        if (editor.value!.getLength() > quilllimit) {
            editor.value!.deleteText(quilllimit, editor.value!.getLength());
        }
        const html = editor.value!.root.innerHTML;
        quillArea.value!.value = editor.value!.root.innerHTML;
        form.additional_info = html;
    });

    quillArea.value.addEventListener('input', () => {
        editor.value!.root.innerHTML = quillArea.value!.value;
    });
});

</script>

<template>

    <Head :title="$t('payment_currency')" />
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
                        {{ $t('select_payment_currency') }}
                    </h2>
                </div>

                <div class="overflow-x-auto rounded-lg shadow">
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg">

                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 text-center" style="width: 5% !important">

                                </th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default"
                                    style="width: 15% !important">
                                    Token
                                </th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default"
                                    style="width: 70% !important">
                                    {{ $t('babel_fee_provider') }}
                                </th>
                                <th class="px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default"
                                    style="width: 10% !important">
                                    {{ $t('rate') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="(asset, index) in props.available_assets" :key="asset.asset_name"
                                class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800">
                                <td class="px-4 py-2 text-center">
                                    <input type="checkbox" :checked="selected === getKey(asset)"
                                        :id="`checkbox-${index}`"
                                        @change="selected = selected === getKey(asset) ? null : getKey(asset)" />
                                </td>
                                <td class="px-4 py-2 truncate max-w-xs">
                                    <component :is="asset.fingerprint ? 'a' : 'div'"
                                        :href="asset.fingerprint ? 'https://cexplorer.io/asset/' + asset.fingerprint : null"
                                        target="_blank" class="flex items-center space-x-2">
                                        <img v-if="asset.logo_url" :src="asset.logo_url" alt="logo"
                                            class="w-6 h-6 rounded" />
                                        <span><tt>{{ asset.asset_name }}</tt></span>
                                    </component>
                                </td>
                                <td class="px-4 py-2 truncate max-w-xs"><tt>{{ asset.provider_name }}</tt></td>
                                <td class="px-6 py-2 text-right">
                                    <tt>{{ formatRate(asset.rate) }}</tt>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                </div>

                <label id="editorLabel"
                    class="block text-sm py-2 mt-4 text-left font-semibold text-gray-700 dark:text-gray-300">{{
                        $t('additional_information')
                    }}</label>

                <div class="overflow-x-auto rounded-lg border">
                    <div ref="quillContainer" aria-labelledby="editorLabel" id="quillContainer" class="mt-4"></div>
                    <textarea rows="3" id="quillArea" ref="quillArea" class="hidden"></textarea>
                </div>

                <div class="h-5"></div>

                <div class="mt-4 flex justify-end">
                    <button type="submit"
                        class="px-4 py-2 rounded-lg shadow cursor-pointer text-white bg-blue-600 hover:bg-blue-700 dark:hover:bg-blue-500 disabled:bg-gray-400 disabled:cursor-not-allowed disabled:opacity-70"
                        :disabled="!selected || form.processing">
                        {{ $t('confirm_transaction') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>