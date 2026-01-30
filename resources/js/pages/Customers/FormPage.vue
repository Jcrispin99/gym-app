<script setup lang="ts">
import FormPageHeader from '@/components/FormPageHeader.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Save } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CustomerForm from './Form.vue';

interface Customer {
    id: number;
}

const props = defineProps<{
    mode: 'create' | 'edit';
    customer_id?: number | null;
}>();

const formRef = ref<any>(null);
const customer = ref<Customer | null>(null);

const isEdit = computed(() => props.mode === 'edit');

const unwrapMaybeRef = <T,>(value: any): T | undefined => {
    if (value && typeof value === 'object' && 'value' in value) {
        return value.value as T;
    }
    return value as T;
};

const isSaving = computed(() => !!unwrapMaybeRef<boolean>(formRef.value?.processing));

const saveLabel = computed(() => (isEdit.value ? 'Guardar Cambios' : 'Guardar'));

const submitForm = () => {
    formRef.value?.submit?.();
};

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Clientes', href: '/customers' },
    {
        title: isEdit.value ? `Editar #${props.customer_id}` : 'Nuevo Cliente',
        href: isEdit.value
            ? `/customers/${props.customer_id}/edit`
            : '/customers/create',
    },
]);

const pageTitle = computed(() =>
    isEdit.value ? `Editar Cliente #${props.customer_id}` : 'Nuevo Cliente',
);

const headerTitle = computed(() => (isEdit.value ? 'Editar Cliente' : 'Nuevo Cliente'));
const headerDescription = computed(() =>
    isEdit.value ? `Cliente #${props.customer_id}` : 'Registra un nuevo cliente',
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
                    back-href="/customers"
                >
                    <template #actions>
                        <Button @click="submitForm" :disabled="isSaving">
                            <Save class="mr-2 h-4 w-4" />
                            {{ isSaving ? 'Guardando...' : saveLabel }}
                        </Button>
                    </template>
                </FormPageHeader>

                <CustomerForm
                    ref="formRef"
                    :mode="mode"
                    :customer-id="customer_id"
                    @loaded="(c) => { customer = c }"
                    @saved="(c) => { customer = c; if (mode === 'create') router.visit(`/customers/${c.id}/edit`) }"
                />
            </div>
        </div>
    </AppLayout>
</template>

