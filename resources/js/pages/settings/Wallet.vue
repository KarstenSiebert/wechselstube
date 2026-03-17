<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { wallet } from '@/routes';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: "wallet_settings",
        href: wallet().url,
    },
];

const props = defineProps({
    address: String,
    qrcode: String,
    keys: String
})

function downloadWalletInfo() {
    const dataStr = JSON.stringify({ keys: props.keys }, null, 2);

    const blob = new Blob([dataStr], { type: "application/json" });
    const url = URL.createObjectURL(blob);

    const link = document.createElement("a");
    link.href = url;
    link.download = "wallet-keys.json";
    link.click();

    URL.revokeObjectURL(url);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">

        <Head :title="$t('wallet_settings')" />

        <SettingsLayout>
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <HeadingSmall :title="$t('wallet_information')" :description="$t('this_is_your_wallet_address')" />

                    <button type="button" @click="downloadWalletInfo"
                        class=" text-xs flex items-center gap-2 px-3 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg shadow hover:bg-gray-300 dark:hover:bg-gray-600 cursor-pointer">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
                        </svg>
                        {{ $t('keys') }}
                    </button>
                </div>

                <HeadingSmall :title="$t('wallet_address')" :description="address" />

                <div class="grid gap-2">
                    <img :src="qrcode" width="234" height="234">
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
