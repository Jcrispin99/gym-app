<script setup lang="ts">
import FormPageHeader from '@/components/FormPageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    CheckCircle,
    FilePlus,
    MoreVertical,
    Save,
    Send,
    Trash2,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import SaleForm from './Form.vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    sale_id?: number | null;
}>();

const formRef = ref<any>(null);

const unwrapMaybeRef = <T,>(value: any): T | undefined => {
    if (value && typeof value === 'object' && 'value' in value) {
        return value.value as T;
    }
    return value as T;
};

const isEdit = computed(() => props.mode === 'edit');
const isSaving = computed(
    () => !!unwrapMaybeRef<boolean>(formRef.value?.processing),
);
const submitDisabled = computed(
    () => !!unwrapMaybeRef<boolean>(formRef.value?.submitDisabled),
);
const sale = computed<any>(() => unwrapMaybeRef<any>(formRef.value?.sale));

const canSendSunat = computed(
    () => !!unwrapMaybeRef<boolean>(formRef.value?.canSendSunat),
);
const canCreateCreditNote = computed(
    () => !!unwrapMaybeRef<boolean>(formRef.value?.canCreateCreditNote),
);

const pageTitle = computed(() =>
    isEdit.value ? `Editar Venta #${props.sale_id}` : 'Crear Venta',
);

const headerTitle = computed(() =>
    isEdit.value ? 'Editar Venta' : 'Crear Venta',
);
const headerDescription = computed(() => {
    if (!isEdit.value) return 'Nueva venta directa';
    const s = sale.value;
    if (s?.serie && s?.correlative) return `${s.serie}-${s.correlative}`;
    return `Venta #${props.sale_id}`;
});

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Ventas', href: '/sales' },
    {
        title: isEdit.value ? `Editar #${props.sale_id}` : 'Crear',
        href: isEdit.value ? `/sales/${props.sale_id}/edit` : '/sales/create',
    },
]);

const submitForm = () => {
    formRef.value?.submit?.();
};

const postThisSale = () => {
    formRef.value?.postSale?.();
};

const cancelThisSale = () => {
    formRef.value?.cancelSale?.();
};

const sendSunat = () => {
    formRef.value?.retrySunat?.();
};

const createCreditNote = () => {
    formRef.value?.createCreditNote?.();
};

const deleteThisSale = () => {
    formRef.value?.deleteSale?.();
};
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="w-full p-4">
            <div class="flex flex-col gap-4">
                <FormPageHeader
                    :title="headerTitle"
                    :description="headerDescription"
                    back-href="/sales"
                >
                    <template #actions>
                        <Button @click="submitForm" :disabled="submitDisabled">
                            <Save class="mr-2 h-4 w-4" />
                            {{ isSaving ? 'Guardando...' : 'Guardar' }}
                        </Button>

                        <DropdownMenu v-if="mode === 'edit'">
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    title="Acciones"
                                >
                                    <MoreVertical class="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    v-if="sale?.status === 'draft'"
                                    @click="postThisSale"
                                >
                                    <CheckCircle class="mr-2 h-4 w-4" />
                                    Publicar
                                </DropdownMenuItem>

                                <DropdownMenuItem
                                    v-if="
                                        sale?.status === 'posted' &&
                                        !sale?.original_sale_id
                                    "
                                    @click="cancelThisSale"
                                >
                                    <XCircle class="mr-2 h-4 w-4" />
                                    Cancelar
                                </DropdownMenuItem>

                                <DropdownMenuItem
                                    v-if="canSendSunat"
                                    @click="sendSunat"
                                >
                                    <Send class="mr-2 h-4 w-4" />
                                    Enviar SUNAT
                                </DropdownMenuItem>

                                <DropdownMenuItem
                                    v-if="canCreateCreditNote"
                                    @click="createCreditNote"
                                >
                                    <FilePlus class="mr-2 h-4 w-4" />
                                    Nota de Crédito
                                </DropdownMenuItem>

                                <DropdownMenuSeparator
                                    v-if="sale?.status === 'draft'"
                                />

                                <DropdownMenuItem
                                    v-if="sale?.status === 'draft'"
                                    class="text-destructive focus:text-destructive"
                                    @click="deleteThisSale"
                                >
                                    <Trash2 class="mr-2 h-4 w-4" />
                                    Eliminar
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </template>
                </FormPageHeader>

                <SaleForm
                    ref="formRef"
                    :mode="mode"
                    :sale-id="sale_id"
                    @saved="
                        (s) => {
                            if (mode === 'create')
                                router.visit(`/sales/${s.id}/edit`);
                        }
                    "
                />
            </div>
        </div>
    </AppLayout>
</template>
