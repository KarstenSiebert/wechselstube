<script setup lang="ts">
import { ref, reactive, computed, onMounted } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { create } from '@/routes/mints';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import FlashMessage from "@/components/FlashMessage.vue";
import "@inertiajs/core"
import Quill from 'quill';
import ImageCompress from 'quill-image-compress';

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
        title: "mint_token",
        href: create().url,
    },
];

interface Category {
    id: number
    name?: string | null
}

const page = usePage()

const quilllimit = 2048;
const editortype = false;

const quillContainer = ref<HTMLDivElement | null>(null);
const quillArea = ref<HTMLTextAreaElement | null>(null);
const editor = ref<Quill | null>(null);

const flashMessage = ref(page.props.flash?.success || page.props.flash?.error || '');

function setFlashFromErrors(errors: Record<string, string | string[]>) {
    flashMessage.value = Object.values(errors)
        .map(val => (Array.isArray(val) ? val[0] : val))
        .join(' ');
}

const searchResults = ref<Category[]>([])

const loading = ref(false)

const searchCache = reactive<Record<string, Category[]>>({})

let searchTimeout: number | undefined

async function searchCategories(query: string) {
    if (!query) {
        searchResults.value = []
        return
    }

    const prefix = Object.keys(searchCache).find(k => query.startsWith(k))
    const safeIncludes = (field: string | null | undefined, q: string) =>
        (field ?? '').toLowerCase().includes(q.toLowerCase())

    if (prefix) {
        searchResults.value = searchCache[prefix].filter(c =>
            safeIncludes(c.name, query)
        )
        return
    }

    loading.value = true
    try {
        const res = await fetch(`/categories/search?q=${encodeURIComponent(query)}`)
        if (res.ok) {
            const data: Category[] = await res.json()
            searchResults.value = data
            searchCache[query] = data
        }
    } finally {
        loading.value = false
    }
}

function handleCategoryInput(query: string) {
    clearTimeout(searchTimeout)
    searchTimeout = window.setTimeout(() => {
        searchCategories(query)
    }, 300)
}

function handleCategoryInputEvent(e: Event) {
    const target = e.target as HTMLInputElement;
    handleCategoryInput(target.value);
}

const rawInput = ref<string>('');

const displayValue = computed<string>({
    get() {
        if (rawInput.value !== '') return rawInput.value;

        if (!form.number) return '';

        const raw = form.number.toString();

        if (form.decimals === 0) return raw;

        const padded = raw.padStart(form.decimals + 1, '0');
        const intPart = padded.slice(0, -form.decimals) || '0';
        const fracPart = padded.slice(-form.decimals);

        return `${intPart}.${fracPart}`;
    },

    set(value: string) {
        rawInput.value = value;

        let s = value.replace(',', '.').replace(/[^0-9.]/g, '');

        if (s === '.') s = '0.';

        if (s === '') {
            form.number = '';
            form.decimals = 0;
            form.errors.number = '';
            return;
        }

        const parts = s.split('.');
        if (parts.length > 2) {
            form.errors.number = 'Invalid number format';
            return;
        }

        const fractional = (parts[1] || '').slice(0, 6);
        form.decimals = fractional.length;

        const integerPart = parts[0] || '0';

        const normalized = integerPart + fractional.padEnd(form.decimals, '0');

        const trimmed = normalized.replace(/^0+(?=\d)/, '') || (normalized === '' ? '0' : normalized);

        form.number = trimmed;
        form.errors.number = '';

        if (/^\d+(\.\d+)?$/.test(s)) {
            rawInput.value = '';
        }
    }
});

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

function selectCategory(category: Category) {
    form.category = category.name || ''
    searchResults.value = []
}

const form = useForm({
    name: '',
    ticker: '',
    number: '',
    category: '',
    link: '',
    decimals: 0,
    short_description: '',
    additional_info: ''
})

function submitForm() {
    form.post("/mints", {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            flashMessage.value = page.props.flash?.success || 'Minted successfully!';
        },
        onError: () => {
            setFlashFromErrors(form.errors);
        }
    });
}

</script>

<template>

    <Head :title="$t('mint_token')" />

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
                        {{ $t('mint_token') }}
                    </h2>
                </div>

                <div class="mb-3 flex gap-4">
                    <div class="flex flex-col flex-1">
                        <label for="name" class="block text-sm font-medium mb-1">{{
                            $t('name') }}</label>
                        <input id="name" v-model="form.name" type="text" class="font-mono w-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 
                  bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200" required autocomplete="off" />
                        <div class="text-red-600 h-5">
                            {{ form.errors.name || '' }}
                        </div>
                    </div>

                    <div class="flex flex-col flex-1">
                        <label for="ticker" class="block text-sm font-medium mb-1">{{
                            $t('ticker') }}</label>
                        <input id="ticker" v-model="form.ticker" type="text" class="font-mono w-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 
                  bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200" required autocomplete="off" />
                        <div class="text-red-600 h-5">
                            {{ form.errors.ticker || '' }}
                        </div>
                    </div>
                </div>

                <div class="mb-3 flex gap-4">
                    <!-- Number input -->
                    <div class="flex flex-col flex-1">
                        <label for="number" class="block text-sm font-medium mb-1">
                            {{ $t('number') }}
                        </label>
                        <input id="number" v-model="displayValue" type="text"
                            class="font-mono w-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200"
                            required autocomplete="off" />
                        <div class="text-red-600 h-5">
                            {{ form.errors.number || '' }}
                        </div>
                    </div>

                    <!-- Category input with dropdown -->
                    <div class="flex flex-col flex-1 relative">
                        <label for="category" class="block text-sm font-medium mb-1">
                            {{ $t('category') }}
                        </label>

                        <input id="category" v-model="form.category" @input="handleCategoryInputEvent" type="text"
                            :placeholder="$t('type_to_search_category')"
                            class="w-full py-1 px-1 rounded border font-mono border-gray-300 dark:border-gray-600 
            bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-500" autocomplete="off" required />

                        <!-- Dropdown wrapper to push below input -->
                        <div class="relative w-full">
                            <ul v-if="searchResults.length"
                                class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded max-h-60 overflow-auto shadow-lg">
                                <li v-for="(category, index) in searchResults" :key="category.id"
                                    @click="selectCategory(category)"
                                    :class="{ 'bg-gray-200 dark:bg-gray-600': index === category.id }"
                                    class="px-2 py-1 font-mono cursor-pointer truncate hover:bg-gray-200 dark:hover:bg-gray-600">
                                    {{ category.name }}
                                </li>
                            </ul>
                        </div>

                        <div class="text-red-600 h-5">
                            {{ form.errors.category || '' }}
                        </div>
                    </div>
                </div>

                <div class="mb-3 flex gap-4">
                    <div class="flex flex-col flex-1">
                        <label for="short_description" class="block text-sm font-medium mb-1">{{
                            $t('short_description') }}</label>
                        <input id="short_description" v-model="form.short_description" type="text" class="font-mono w-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 
                  bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200" required autocomplete="off" />
                        <div class="text-red-600 h-5">
                            {{ form.errors.short_description || '' }}
                        </div>
                    </div>

                    <div class="flex flex-col flex-1">
                        <label for="link" class="block text-sm font-medium mb-1">{{
                            $t('link') }}</label>
                        <input id="link" v-model="form.link" type="text" class="font-mono w-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 
                  bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200" required autocomplete="off" />
                        <div class="text-red-600 h-5">
                            {{ form.errors.link || '' }}
                        </div>
                    </div>
                </div>

                <label id="editorLabel" class="block text-sm mt-4 text-left font-medium mb-1">{{
                    $t('description')
                }}</label>

                <div class="overflow-x-auto rounded-lg border">
                    <div ref="quillContainer" aria-labelledby="editorLabel" id="quillContainer" class="mt-4"></div>
                    <textarea id="quillArea" rows="3" ref="quillArea" class="hidden"></textarea>
                </div>

                <div class="h-5"></div>

                <div class="mt-4 flex justify-end">
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 dark:hover:bg-blue-500 disabled:opacity-50 cursor-pointer"
                        :disabled="form.processing">
                        {{ $t('token_mint') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>