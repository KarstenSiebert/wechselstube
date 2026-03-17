<script setup lang="ts">
import { ref, reactive } from "vue";

interface Props<T> {
    /** The endpoint to call, e.g. "/users/search" */
    endpoint: string;
    /** v-model binding for selected value */
    modelValue: T | null;
    /** How to render a result item */
    display: (item: T) => string;
    /** Called when a result is selected */
    onSelect: (item: T) => void;
    /** Optional placeholder */
    placeholder?: string;
}

const props = defineProps<Props<any>>();
const emit = defineEmits(["update:modelValue"]);

const query = ref("");
const results = ref<any[]>([]);
const cache = reactive<Record<string, any[]>>({});
const loading = ref(false);
let timeout: number | undefined;

async function search(q: string) {
    if (!q) {
        results.value = [];
        return;
    }

    const prefix = Object.keys(cache).find((k) => q.startsWith(k));
    if (prefix) {
        results.value = cache[prefix];
        return;
    }

    loading.value = true;
    try {
        const res = await fetch(`${props.endpoint}?q=${encodeURIComponent(q)}`);
        if (res.ok) {
            const data = await res.json();
            results.value = data;
            cache[q] = data;
        }
    } finally {
        loading.value = false;
    }
}

function handleInput(e: Event) {
    const target = e.target as HTMLInputElement;
    query.value = target.value;
    clearTimeout(timeout);
    timeout = window.setTimeout(() => search(query.value), 300);
}

function select(item: any) {
    emit("update:modelValue", item);
    props.onSelect(item);
    results.value = [];
    query.value = props.display(item); // put readable text in input
}
</script>

<template>
    <div class="relative">
        <input :placeholder="placeholder"
            class="w-full py-1 px-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-500"
            :value="query" @input="handleInput" autocomplete="off" />

        <ul v-if="results.length"
            class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded max-h-60 overflow-auto shadow-lg">
            <li v-for="item in results" :key="item.id" @click="select(item)"
                class="px-2 py-1 font-mono cursor-pointer truncate hover:bg-gray-200 dark:hover:bg-gray-600">
                {{ display(item) }}
            </li>
        </ul>
    </div>
</template>
