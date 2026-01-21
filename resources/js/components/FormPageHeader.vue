<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

interface Props {
    title: string;
    description?: string;
    backHref?: string;
    showBack?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    description: undefined,
    backHref: undefined,
    showBack: true,
});

const goBack = () => {
    if (props.backHref) {
        router.visit(props.backHref);
        return;
    }

    window.history.back();
};
</script>

<template>
    <div class="mb-6 flex items-center justify-between gap-4">
        <div class="flex min-w-0 items-center gap-4">
            <Button
                v-if="showBack"
                variant="ghost"
                size="icon"
                type="button"
                @click="goBack"
            >
                <ArrowLeft class="h-5 w-5" />
            </Button>
            <div class="min-w-0">
                <h1 class="truncate text-3xl font-bold tracking-tight">
                    {{ title }}
                </h1>
                <p v-if="description" class="truncate text-muted-foreground">
                    {{ description }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
