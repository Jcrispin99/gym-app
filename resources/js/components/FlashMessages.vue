<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type FlashPayload = {
    success?: string | null;
    error?: string | null;
    info?: string | null;
};

const page = usePage<{ flash?: FlashPayload }>();
const flash = computed(() => page.props.flash || {});

const dismissed = ref<{ success: boolean; error: boolean; info: boolean }>({
    success: false,
    error: false,
    info: false,
});

watch(
    () => flash.value,
    () => {
        dismissed.value = { success: false, error: false, info: false };
    },
    { deep: true },
);

const items = computed(() => {
    const result: Array<{ key: 'success' | 'error' | 'info'; message: string; classes: string }> = [];

    if (flash.value.success && !dismissed.value.success) {
        result.push({
            key: 'success',
            message: flash.value.success,
            classes: 'border-green-200 bg-green-50 text-green-900',
        });
    }
    if (flash.value.error && !dismissed.value.error) {
        result.push({
            key: 'error',
            message: flash.value.error,
            classes: 'border-red-200 bg-red-50 text-red-900',
        });
    }
    if (flash.value.info && !dismissed.value.info) {
        result.push({
            key: 'info',
            message: flash.value.info,
            classes: 'border-blue-200 bg-blue-50 text-blue-900',
        });
    }

    return result;
});

const dismiss = (key: 'success' | 'error' | 'info') => {
    dismissed.value[key] = true;
};
</script>

<template>
    <div v-if="items.length" class="mx-auto w-full px-4 pt-4 md:max-w-7xl">
        <div class="space-y-2">
            <div
                v-for="item in items"
                :key="item.key"
                class="flex items-start justify-between gap-3 rounded-md border px-3 py-2 text-sm"
                :class="item.classes"
            >
                <div class="min-w-0 whitespace-pre-wrap">
                    {{ item.message }}
                </div>
                <button
                    type="button"
                    class="shrink-0 rounded px-2 py-1 text-xs font-medium opacity-80 hover:opacity-100"
                    @click="dismiss(item.key)"
                >
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</template>

