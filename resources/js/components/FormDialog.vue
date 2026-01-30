<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Save } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description?: string;
        submitLabel?: string;
        submittingLabel?: string;
        cancelLabel?: string;
        contentClass?: string;
        formRef?: any;
    }>(),
    {
        description: undefined,
        submitLabel: 'Crear',
        submittingLabel: 'Guardando...',
        cancelLabel: 'Cancelar',
        contentClass: 'sm:max-w-[700px]',
        formRef: undefined,
    },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const isSubmitting = computed(() => !!props.formRef?.processing?.value);

const close = () => emit('update:open', false);

const submit = () => {
    props.formRef?.submit?.();
};
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent :class="contentClass">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <slot />

            <DialogFooter>
                <Button variant="outline" @click="close">
                    {{ cancelLabel }}
                </Button>
                <Button @click="submit" :disabled="isSubmitting">
                    <Save class="mr-2 h-4 w-4" />
                    {{ isSubmitting ? submittingLabel : submitLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
