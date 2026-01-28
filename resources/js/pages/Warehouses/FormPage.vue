<script setup lang="ts">
import FormPageHeader from '@/components/FormPageHeader.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Save } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import WarehouseForm from './Form.vue';

interface Company {
    id: number;
    business_name?: string | null;
    trade_name?: string | null;
}

interface Warehouse {
    id: number;
    name: string;
    location: string | null;
    company_id: number;
    company?: Company;
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    mode: 'create' | 'edit';
    warehouse_id?: number | null;
}>();

const warehouse = ref<Warehouse | null>(null);
const formRef = ref<any>(null);

const isEdit = computed(() => props.mode === 'edit');

const companyLabel = (company: Company) => {
    return (
        company.trade_name || company.business_name || `Empresa ${company.id}`
    );
};

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Almacenes', href: '/warehouses' },
    {
        title: isEdit.value ? warehouse.value?.name || 'Editar' : 'Nuevo',
        href: isEdit.value
            ? `/warehouses/${props.warehouse_id}/edit`
            : '/warehouses/create',
    },
]);

const pageTitle = computed(() =>
    isEdit.value
        ? warehouse.value?.name
            ? `Editar - ${warehouse.value.name}`
            : 'Editar Almacén'
        : 'Nuevo Almacén',
);

const headerTitle = computed(() =>
    isEdit.value ? warehouse.value?.name || 'Editar Almacén' : 'Nuevo Almacén',
);

const headerDescription = computed(() => {
    if (!isEdit.value) return 'Crea un nuevo almacén para gestionar inventario';
    return warehouse.value?.company ? companyLabel(warehouse.value.company) : '';
});

const saveLabel = computed(() =>
    isEdit.value ? 'Guardar Cambios' : 'Guardar',
);

const isSaving = computed(() => !!formRef.value?.processing?.value);

const submitForm = () => {
    formRef.value?.submit?.();
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleString('es-PE');
};
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <FormPageHeader
                :title="headerTitle"
                :description="headerDescription"
                back-href="/warehouses"
            >
                <template #actions>
                    <Button @click="submitForm" :disabled="isSaving">
                        <Save class="mr-2 h-4 w-4" />
                        {{ isSaving ? 'Guardando...' : saveLabel }}
                    </Button>
                </template>
            </FormPageHeader>

            <WarehouseForm
                ref="formRef"
                :mode="mode"
                :warehouse-id="warehouse_id"
                @loaded="(w) => { warehouse = w }"
                @saved="(w) => { warehouse = w; if (mode === 'create') router.visit(`/warehouses/${w.id}/edit`) }"
            >
                <template v-if="isEdit" #sidebar="{ warehouse: w }">
                    <Card>
                        <CardHeader>
                            <CardTitle>Información</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3 text-sm">
                            <div>
                                <p class="font-medium">Estado</p>
                                <Badge variant="default">Activo</Badge>
                            </div>
                            <div v-if="w?.company">
                                <p class="font-medium">Empresa</p>
                                <p class="text-muted-foreground">
                                    {{ companyLabel(w.company) }}
                                </p>
                            </div>
                            <div v-if="w?.created_at">
                                <p class="font-medium">Creado</p>
                                <p class="text-muted-foreground">
                                    {{ formatDate(w.created_at) }}
                                </p>
                            </div>
                            <div v-if="w?.updated_at">
                                <p class="font-medium">Último cambio</p>
                                <p class="text-muted-foreground">
                                    {{ formatDate(w.updated_at) }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Ayuda</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm">
                            <p class="text-muted-foreground">
                                Los campos marcados con * son obligatorios.
                            </p>
                            <p class="text-muted-foreground">
                                El código debe ser único y fácil de identificar.
                            </p>
                            <p class="text-muted-foreground">
                                La ubicación ayuda a localizar físicamente el
                                almacén.
                            </p>
                        </CardContent>
                    </Card>
                </template>
            </WarehouseForm>
        </div>
    </AppLayout>
</template>
