<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    title?: string;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'POS',
});

const pageTitle = computed(() => props.title);
</script>

<template>
    <Head :title="pageTitle" />

    <div class="min-h-screen bg-background">
        <!-- Header -->
        <header class="border-b bg-card">
            <div class="container mx-auto px-4 py-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <h1 class="text-xl font-bold">{{ pageTitle }}</h1>
                    </div>
                    <div class="flex items-center gap-2">
                        <slot name="header-actions" />
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto p-4">
            <slot />
        </main>
    </div>
</template>
