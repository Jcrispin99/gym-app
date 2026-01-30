<script setup lang="ts">
import FormPageHeader from '@/components/FormPageHeader.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Save } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PosConfigForm from './Form.vue';

interface PosConfig {
    id: number;
    name: string;
}

const props = defineProps<{
    mode: 'create' | 'edit';
    pos_config_id?: number | null;
}>();

const formRef = ref<any>(null);
const posConfig = ref<PosConfig | null>(null);

const isEdit = computed(() => props.mode === 'edit');
const isSaving = computed(() => !!formRef.value?.processing?.value);

const saveLabel = computed(() => (isEdit.value ? 'Guardar Cambios' : 'Crear POS'));

const submitForm = () => {
    formRef.value?.submit?.();
};

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'POS', href: '/pos-configs' },
    {
        title: isEdit.value
            ? posConfig.value?.name || `Editar #${props.pos_config_id}`
            : 'Nuevo POS',
        href: isEdit.value
            ? `/pos-configs/${props.pos_config_id}/edit`
            : '/pos-configs/create',
    },
]);

const pageTitle = computed(() =>
    isEdit.value
        ? posConfig.value?.name
            ? `Editar ${posConfig.value.name}`
            : 'Editar POS'
        : 'Nuevo POS',
);

const headerTitle = computed(() => (isEdit.value ? 'Editar POS' : 'Nuevo POS'));

const headerDescription = computed(() =>
    isEdit.value ? posConfig.value?.name || '' : 'Configura el punto de venta',
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
                    back-href="/pos-configs"
                >
                    <template #actions>
                        <Button @click="submitForm" :disabled="isSaving">
                            <Save class="mr-2 h-4 w-4" />
                            {{ isSaving ? 'Guardando...' : saveLabel }}
                        </Button>
                    </template>
                </FormPageHeader>

                <PosConfigForm
                    ref="formRef"
                    :mode="mode"
                    :pos-config-id="pos_config_id"
                    @loaded="(p) => { posConfig = p }"
                    @saved="(p) => { posConfig = p; if (mode === 'create') router.visit(`/pos-configs/${p.id}/edit`) }"
                />
            </div>
        </div>
    </AppLayout>
</template>

