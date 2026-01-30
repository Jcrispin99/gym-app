<script setup lang="ts">
import FormPageHeader from '@/components/FormPageHeader.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Save } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PurchaseForm from './Form.vue';

interface Purchase {
    id: number;
    status: 'draft' | 'posted' | 'cancelled';
}

const props = defineProps<{
    mode: 'create' | 'edit';
    purchase_id?: number | null;
}>();

const formRef = ref<any>(null);
const purchase = ref<Purchase | null>(null);

const isEdit = computed(() => props.mode === 'edit');

const unwrapMaybeRef = <T,>(value: any): T | undefined => {
    if (value && typeof value === 'object' && 'value' in value) {
        return value.value as T;
    }
    return value as T;
};

const isSaving = computed(() => {
    return !!unwrapMaybeRef<boolean>(formRef.value?.processing);
});
const isEditable = computed(() => {
    if (!isEdit.value) return true;
    return !!unwrapMaybeRef<boolean>(formRef.value?.isEditable);
});

const saveLabel = computed(() =>
    isEdit.value ? 'Actualizar Compra' : 'Crear Compra',
);

const submitForm = () => {
    formRef.value?.submit?.();
};

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Compras', href: '/purchases' },
    {
        title: isEdit.value ? `Editar #${props.purchase_id}` : 'Nueva Compra',
        href: isEdit.value
            ? `/purchases/${props.purchase_id}/edit`
            : '/purchases/create',
    },
]);

const pageTitle = computed(() =>
    isEdit.value ? `Editar Compra #${props.purchase_id}` : 'Nueva Compra',
);

const headerTitle = computed(() =>
    isEdit.value ? 'Editar Compra' : 'Nueva Compra',
);
const headerDescription = computed(() =>
    isEdit.value
        ? `Compra #${props.purchase_id}`
        : 'Crear una nueva compra de productos',
);
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="w-full p-4">
            <div class="flex flex-col gap-4">
                <FormPageHeader
                    :title="headerTitle"
                    :description="headerDescription"
                    back-href="/purchases"
                >
                    <template #actions>
                        <Button
                            @click="submitForm"
                            :disabled="isSaving || !isEditable"
                        >
                            <Save class="mr-2 h-4 w-4" />
                            {{ isSaving ? 'Guardando...' : saveLabel }}
                        </Button>
                    </template>
                </FormPageHeader>

                <PurchaseForm
                    ref="formRef"
                    :mode="mode"
                    :purchase-id="purchase_id"
                    @loaded="
                        (p) => {
                            purchase = p;
                        }
                    "
                    @saved="
                        (p) => {
                            purchase = p;
                            if (mode === 'create')
                                router.visit(`/purchases/${p.id}/edit`);
                        }
                    "
                />
            </div>
        </div>
    </AppLayout>
</template>
