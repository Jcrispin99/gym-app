<script setup lang="ts">
import FormPageHeader from '@/components/FormPageHeader.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Save } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CategoryForm from './Form.vue';

interface Category {
    id: number;
    name: string;
    is_active: boolean;
}

const props = defineProps<{
    mode: 'create' | 'edit';
    category_id?: number | null;
}>();

const formRef = ref<any>(null);
const category = ref<Category | null>(null);

const isEdit = computed(() => props.mode === 'edit');
const isSaving = computed(() => !!formRef.value?.processing?.value);

const saveLabel = computed(() =>
    isEdit.value ? 'Actualizar Categoría' : 'Crear Categoría',
);

const submitForm = () => {
    formRef.value?.submit?.();
};

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Categorías', href: '/categories' },
    {
        title: isEdit.value ? category.value?.name || 'Editar' : 'Nueva Categoría',
        href: isEdit.value
            ? `/categories/${props.category_id}/edit`
            : '/categories/create',
    },
]);

const pageTitle = computed(() =>
    isEdit.value
        ? category.value?.name
            ? `Editar ${category.value.name}`
            : 'Editar Categoría'
        : 'Nueva Categoría',
);

const headerTitle = computed(() =>
    isEdit.value ? 'Editar Categoría' : 'Nueva Categoría',
);

const headerDescription = computed(() =>
    isEdit.value
        ? category.value?.name || ''
        : 'Crear una nueva categoría de producto',
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
                    back-href="/categories"
                >
                    <template #actions>
                        <Button @click="submitForm" :disabled="isSaving">
                            <Save class="mr-2 h-4 w-4" />
                            {{ isSaving ? 'Guardando...' : saveLabel }}
                        </Button>
                    </template>
                </FormPageHeader>

                <CategoryForm
                    ref="formRef"
                    :mode="mode"
                    :category-id="category_id"
                    @loaded="(c) => { category = c }"
                    @saved="(c) => { category = c; if (mode === 'create') router.visit(`/categories/${c.id}/edit`) }"
                />
            </div>
        </div>
    </AppLayout>
</template>

