<script setup lang="ts">
import FormPageHeader from '@/components/FormPageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    DoorOpen,
    History,
    Pencil,
    Plus,
    Power,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface Tax {
    id: number;
    name: string;
    rate_percent: number;
}

interface Warehouse {
    id: number;
    name: string;
}

interface Journal {
    id: number;
    name: string;
    code: string;
}

interface PosConfig {
    id: number;
    name: string;
    warehouse: Warehouse;
    tax?: Tax;
    apply_tax: boolean;
    prices_include_tax: boolean;
    is_active: boolean;
    journals: Journal[];
    created_at: string;
}

const searchQuery = ref('');
const isLoading = ref(false);

const posConfigs = ref<PosConfig[]>([]);
const meta = ref({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'POS', href: '/pos-configs' },
];

const headers = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

const loadPosConfigs = async (page = 1) => {
    isLoading.value = true;
    try {
        const response = await axios.get('/api/pos-configs', {
            headers,
            params: {
                search: searchQuery.value || undefined,
                per_page: meta.value.per_page,
                page,
            },
        });

        posConfigs.value = (response.data?.data || []) as PosConfig[];
        meta.value = {
            current_page: Number(response.data?.meta?.current_page || 1),
            last_page: Number(response.data?.meta?.last_page || 1),
            per_page: Number(response.data?.meta?.per_page || 20),
            total: Number(response.data?.meta?.total || 0),
        };
    } catch (e) {
        console.error('Error loading POS configs:', e);
        posConfigs.value = [];
        meta.value = {
            current_page: 1,
            last_page: 1,
            per_page: meta.value.per_page,
            total: 0,
        };
    } finally {
        isLoading.value = false;
    }
};

const totalLabel = computed(() =>
    meta.value.total > 0 ? meta.value.total : posConfigs.value.length,
);

const handleSearch = () => {
    loadPosConfigs(1);
};

const toggleStatus = async (posConfig: PosConfig) => {
    try {
        const response = await axios.post(
            `/api/pos-configs/${posConfig.id}/toggle-status`,
            {},
            { headers },
        );
        const updated = response.data?.data as PosConfig;
        posConfigs.value = posConfigs.value.map((p) =>
            p.id === posConfig.id ? updated : p,
        );
    } catch (e) {
        console.error('Error toggling status:', e);
    }
};

const deletePosConfig = async (posConfig: PosConfig) => {
    if (confirm(`¿Estás seguro de eliminar el POS "${posConfig.name}"?`)) {
        try {
            await axios.delete(`/api/pos-configs/${posConfig.id}`, { headers });
            posConfigs.value = posConfigs.value.filter(
                (p) => p.id !== posConfig.id,
            );
        } catch (e) {
            console.error('Error deleting POS config:', e);
        }
    }
};

onMounted(() => loadPosConfigs(1));
</script>

<template>
    <Head title="Configuración POS" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <FormPageHeader
                title="Configuración POS"
                description="Gestiona los puntos de venta"
                :show-back="false"
            >
                <template #actions>
                    <Button @click="router.visit('/pos-configs/create')">
                        <Plus class="mr-2 h-4 w-4" />
                        Nuevo POS
                    </Button>
                </template>
            </FormPageHeader>

            <!-- Search -->
            <Card>
                <CardContent class="pt-6">
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <Search
                                class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="searchQuery"
                                placeholder="Buscar por nombre..."
                                class="pl-10"
                                @keyup.enter="handleSearch"
                            />
                        </div>
                        <Button @click="handleSearch">Buscar</Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Table -->
            <Card>
                <CardHeader>
                    <CardTitle>POS Registrados ({{ totalLabel }})</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Almacén</TableHead>
                                <TableHead>Impuesto</TableHead>
                                <TableHead>Journals</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead class="text-right"
                                    >Acciones</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="posConfig in posConfigs"
                                :key="posConfig.id"
                            >
                                <TableCell class="font-medium">
                                    {{ posConfig.name }}
                                </TableCell>
                                <TableCell>
                                    {{ posConfig.warehouse.name }}
                                </TableCell>
                                <TableCell>
                                    <div v-if="posConfig.tax">
                                        {{ posConfig.tax.name }}
                                        <span
                                            class="text-sm text-muted-foreground"
                                        >
                                            ({{ posConfig.tax.rate_percent }}%)
                                        </span>
                                    </div>
                                    <span v-else class="text-muted-foreground"
                                        >-</span
                                    >
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline">
                                        {{ posConfig.journals.length }} diarios
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="
                                            posConfig.is_active
                                                ? 'default'
                                                : 'secondary'
                                        "
                                    >
                                        {{
                                            posConfig.is_active
                                                ? 'Activo'
                                                : 'Inactivo'
                                        }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            v-if="posConfig.is_active"
                                            size="icon"
                                            @click="
                                                $inertia.visit(
                                                    `/pos/open?config=${posConfig.id}`,
                                                )
                                            "
                                            title="Abrir Caja"
                                        >
                                            <DoorOpen class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            @click="
                                                $inertia.visit(
                                                    `/pos-configs/${posConfig.id}/sessions`,
                                                )
                                            "
                                            title="Ver historial de sesiones"
                                        >
                                            <History class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            @click="
                                                $inertia.visit(
                                                    `/pos-configs/${posConfig.id}/edit`,
                                                )
                                            "
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            :class="
                                                posConfig.is_active
                                                    ? 'text-green-600'
                                                    : 'text-gray-400'
                                            "
                                            @click="toggleStatus(posConfig)"
                                        >
                                            <Power class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="text-destructive"
                                            @click="deletePosConfig(posConfig)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>

                            <TableRow v-if="posConfigs.length === 0">
                                <TableCell
                                    colspan="6"
                                    class="py-8 text-center text-muted-foreground"
                                >
                                    <span v-if="isLoading">Cargando...</span>
                                    <span v-else
                                        >No hay configuraciones POS
                                        registradas</span
                                    >
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
