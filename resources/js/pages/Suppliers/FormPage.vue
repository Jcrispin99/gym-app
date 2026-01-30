<script setup lang="ts">
import FormPageHeader from '@/components/FormPageHeader.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Save } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import SupplierForm from './Form.vue';

interface Supplier {
    id: number;
}

const props = defineProps<{
    mode: 'create' | 'edit';
    supplier_id?: number | null;
}>();

const formRef = ref<any>(null);
const supplier = ref<Supplier | null>(null);

const isEdit = computed(() => props.mode === 'edit');

const unwrapMaybeRef = <T,>(value: any): T | undefined => {
    if (value && typeof value === 'object' && 'value' in value) {
        return value.value as T;
    }
    return value as T;
};

const isSaving = computed(() => !!unwrapMaybeRef<boolean>(formRef.value?.processing));

const saveLabel = computed(() =>
    isEdit.value ? 'Guardar Cambios' : 'Guardar',
);

const submitForm = () => {
    formRef.value?.submit?.();
};

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Proveedores', href: '/suppliers' },
    {
        title: isEdit.value ? `Editar #${props.supplier_id}` : 'Nuevo Proveedor',
        href: isEdit.value
            ? `/suppliers/${props.supplier_id}/edit`
            : '/suppliers/create',
    },
]);

const pageTitle = computed(() =>
    isEdit.value ? `Editar Proveedor #${props.supplier_id}` : 'Nuevo Proveedor',
);

const headerTitle = computed(() =>
    isEdit.value ? 'Editar Proveedor' : 'Nuevo Proveedor',
);
const headerDescription = computed(() =>
    isEdit.value ? `Proveedor #${props.supplier_id}` : 'Registra un nuevo proveedor',
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
                    back-href="/suppliers"
                >
                    <template #actions>
                        <Button @click="submitForm" :disabled="isSaving">
                            <Save class="mr-2 h-4 w-4" />
                            {{ isSaving ? 'Guardando...' : saveLabel }}
                        </Button>
                    </template>
                </FormPageHeader>

                <SupplierForm
                    ref="formRef"
                    :mode="mode"
                    :supplier-id="supplier_id"
                    @loaded="(s) => { supplier = s }"
                    @saved="(s) => { supplier = s; if (mode === 'create') router.visit(`/suppliers/${s.id}/edit`) }"
                />
            </div>
        </div>
    </AppLayout>
</template>
