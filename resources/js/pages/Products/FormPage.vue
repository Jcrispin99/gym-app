<script setup lang="ts">
import FormPageHeader from '@/components/FormPageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Clock, Save, User } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ProductForm from './Form.vue';

interface Activity {
    id: number;
    description: string;
    event: string;
    properties: any;
    created_at: string;
    causer?: {
        name: string;
        email: string;
    };
}

interface Product {
    id: number;
    name: string;
}

const props = defineProps<{
    mode: 'create' | 'edit';
    product_id?: number | null;
}>();

const page = usePage();
const formRef = ref<any>(null);
const product = ref<Product | null>(null);

const isEdit = computed(() => props.mode === 'edit');
const isSaving = computed(() => !!formRef.value?.processing?.value);

const activities = computed<Activity[]>(() => {
    const fromProps = (page.props as any)?.activities;
    return Array.isArray(fromProps) ? fromProps : [];
});

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Productos', href: '/products' },
    {
        title: isEdit.value ? product.value?.name || 'Editar' : 'Nuevo Producto',
        href: isEdit.value
            ? `/products/${props.product_id}/edit`
            : '/products/create',
    },
]);

const pageTitle = computed(() =>
    isEdit.value
        ? product.value?.name
            ? `Editar ${product.value.name}`
            : 'Editar Producto'
        : 'Nuevo Producto',
);

const submitForm = () => {
    formRef.value?.submit?.();
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleString('es-PE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const handleSaved = (savedProduct: any) => {
    product.value = savedProduct;
    if (props.mode === 'create') {
        router.visit(`/products/${savedProduct.id}/edit`);
    }
};
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="w-full p-4">
            <div class="flex flex-col gap-4">
                <FormPageHeader
                    :title="isEdit ? 'Editar Producto' : 'Nuevo Producto'"
                    :description="isEdit ? product?.name || '' : 'Crea un nuevo producto'"
                    back-href="/products"
                >
                    <template #actions>
                        <Button @click="submitForm" :disabled="isSaving">
                            <Save class="mr-2 h-4 w-4" />
                            {{ isSaving ? 'Guardando...' : (isEdit ? 'Actualizar Producto' : 'Crear Producto') }}
                        </Button>
                    </template>
                </FormPageHeader>

                <div
                    class="grid grid-cols-1"
                    :class="isEdit && activities.length > 0 ? 'lg:grid-cols-3' : ''"
                >
                    <!-- Main Content-->
                    <div :class="isEdit && activities.length > 0 ? 'lg:col-span-2' : ''">
                        <ProductForm
                            ref="formRef"
                            :mode="mode"
                            :product-id="product_id"
                            @loaded="(p) => product = p"
                            @saved="handleSaved"
                        />
                    </div>

                    <!-- Activity Log Sidebar (Right) - Solo en edición -->
                    <div
                        v-if="isEdit && activities.length > 0"
                        class="mt-6 lg:col-span-1 lg:mt-0 lg:pl-6"
                    >
                        <Card class="sticky top-4">
                            <CardHeader>
                                <CardTitle>Historial de Cambios</CardTitle>
                                <CardDescription>Últimas 20 actividades</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-4">
                                    <div
                                        v-for="(activity, index) in activities"
                                        :key="index"
                                        class="flex gap-3 text-sm"
                                    >
                                        <div class="flex-shrink-0">
                                            <Clock class="h-4 w-4 text-muted-foreground" />
                                        </div>
                                        <div class="flex-1 space-y-1">
                                            <p class="font-medium">{{ activity.description }}</p>
                                            <p class="text-xs text-muted-foreground">
                                                {{ formatDate(activity.created_at) }}
                                            </p>
                                            <p
                                                v-if="activity.causer"
                                                class="flex items-center gap-1 text-xs text-muted-foreground"
                                            >
                                                <User class="h-3 w-3" />
                                                {{ activity.causer.name }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
