<script setup lang="ts">
import FormPageHeader from '@/components/FormPageHeader.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Save } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AttributeForm from './Form.vue';

interface Attribute {
    id: number;
    name: string;
    is_active: boolean;
}

const props = defineProps<{
    mode: 'create' | 'edit';
    attribute_id?: number | null;
}>();

const formRef = ref<any>(null);
const attribute = ref<Attribute | null>(null);

const isEdit = computed(() => props.mode === 'edit');
const isSaving = computed(() => !!formRef.value?.processing?.value);

const saveLabel = computed(() =>
    isEdit.value ? 'Actualizar Atributo' : 'Crear Atributo',
);

const submitForm = () => {
    formRef.value?.submit?.();
};

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Atributos', href: '/attributes' },
    {
        title: isEdit.value ? attribute.value?.name || 'Editar' : 'Nuevo Atributo',
        href: isEdit.value
            ? `/attributes/${props.attribute_id}/edit`
            : '/attributes/create',
    },
]);

const pageTitle = computed(() =>
    isEdit.value
        ? attribute.value?.name
            ? `Editar ${attribute.value.name}`
            : 'Editar Atributo'
        : 'Nuevo Atributo',
);

const headerTitle = computed(() =>
    isEdit.value ? 'Editar Atributo' : 'Nuevo Atributo',
);

const headerDescription = computed(() =>
    isEdit.value ? attribute.value?.name || '' : 'Crear un nuevo atributo con sus valores',
);
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <FormPageHeader
                :title="headerTitle"
                :description="headerDescription"
                back-href="/attributes"
            >
                <template #actions>
                    <Button @click="submitForm" :disabled="isSaving">
                        <Save class="mr-2 h-4 w-4" />
                        {{ isSaving ? 'Guardando...' : saveLabel }}
                    </Button>
                </template>
            </FormPageHeader>

            <AttributeForm
                ref="formRef"
                :mode="mode"
                :attribute-id="attribute_id"
                @loaded="(a) => { attribute = a }"
                @saved="(a) => { attribute = a; if (mode === 'create') router.visit(`/attributes/${a.id}/edit`) }"
            />
        </div>
    </AppLayout>
</template>

