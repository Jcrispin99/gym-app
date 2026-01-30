<script setup lang="ts">
import FormPageHeader from '@/components/FormPageHeader.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
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
    CheckCircle,
    Edit,
    Plus,
    Search,
    ShoppingCart,
    Trash2,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface Sale {
    id: number;
    serie: string | null;
    correlative: string | null;
    status: 'draft' | 'posted' | 'cancelled';
    payment_status: string;
    total: number;
    sunat_status?: string;
    sunat_response?: { accepted?: boolean; error?: string | null } | null;
    partner: {
        id: number;
        first_name: string;
        last_name: string;
        business_name: string | null;
    } | null;
    warehouse: {
        id: number;
        name: string;
    };
    created_at: string;
}

const sales = ref<Sale[]>([]);
const isLoading = ref(false);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Ventas', href: '/sales' },
];

const deleteDialogOpen = ref(false);
const saleToDelete = ref<Sale | null>(null);
const searchQuery = ref('');

const headers = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

const loadSales = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/api/sales', {
            headers,
            params: {
                search: searchQuery.value || undefined,
                per_page: 50,
            },
        });
        sales.value = (response.data?.data || []) as Sale[];
    } catch (e) {
        console.error('Error loading sales:', e);
        sales.value = [];
    } finally {
        isLoading.value = false;
    }
};

const openDeleteDialog = (sale: Sale) => {
    saleToDelete.value = sale;
    deleteDialogOpen.value = true;
};

const deleteSale = async () => {
    const target = saleToDelete.value;
    if (!target) return;

    try {
        await axios.delete(`/api/sales/${target.id}`, { headers });
        sales.value = sales.value.filter((s) => s.id !== target.id);
        deleteDialogOpen.value = false;
        saleToDelete.value = null;
    } catch (e) {
        console.error('Error deleting sale:', e);
    }
};

const postSale = (sale: Sale) => {
    if (
        confirm(
            `¿Estás seguro de publicar esta venta? Se asignará el número y se reducirá el inventario.`,
        )
    ) {
        axios
            .post(`/api/sales/${sale.id}/post`, {}, { headers })
            .then(loadSales)
            .catch((e) => console.error('Error posting sale:', e));
    }
};

const performSearch = () => {
    loadSales();
};

const totalSales = computed(() => sales.value.length);
const draftSales = computed(
    () => sales.value.filter((s) => s.status === 'draft').length,
);
const postedSales = computed(
    () => sales.value.filter((s) => s.status === 'posted').length,
);

const getStatusBadge = (status: string) => {
    const badges: Record<string, any> = {
        draft: { label: 'Borrador', variant: 'secondary' },
        posted: { label: 'Publicado', variant: 'default' },
        cancelled: { label: 'Cancelado', variant: 'destructive' },
    };
    return badges[status] || { label: status, variant: 'outline' };
};

const getDisplayNumber = (sale: Sale) => {
    if (sale.serie && sale.correlative) {
        return `${sale.serie}-${sale.correlative}`;
    }
    return `Borrador #${sale.id}`;
};

const openSale = (sale: Sale) => {
    router.visit(`/sales/${sale.id}/edit`, { preserveScroll: true });
};

const getPartnerName = (sale: Sale) => {
    const p = sale.partner;
    if (!p) return 'Cliente General';
    return p.business_name || `${p.first_name} ${p.last_name}`.trim();
};

const getSunatBadge = (sale: Sale) => {
    const status = sale.sunat_status || 'pending';
    const accepted = sale.sunat_response?.accepted === true;

    const map: Record<string, { label: string; variant: any }> = {
        pending: { label: 'Pendiente', variant: 'secondary' },
        processing: { label: 'Procesando', variant: 'secondary' },
        sent: { label: 'Enviado', variant: 'outline' },
        accepted: { label: 'Aceptado', variant: 'default' },
        error: { label: 'Error', variant: 'destructive' },
        skipped: { label: 'Omitido', variant: 'outline' },
    };

    if (accepted) return map.accepted;
    return map[status] || { label: status, variant: 'outline' };
};

onMounted(loadSales);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Ventas" />

        <div class="flex flex-col gap-4 p-4">
            <FormPageHeader
                title="Ventas"
                description="Gestiona ventas directas y borradores"
                :show-back="false"
            >
                <template #actions>
                    <Button @click="router.visit('/sales/create')">
                        <Plus class="mr-2 h-4 w-4" />
                        Nueva Venta
                    </Button>
                </template>
            </FormPageHeader>

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Total Ventas</CardTitle
                        >
                        <ShoppingCart class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ totalSales }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Borradores</CardTitle
                        >
                        <Edit class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ draftSales }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Publicadas</CardTitle
                        >
                        <CheckCircle class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ postedSales }}</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Search Bar -->
            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <Search
                        class="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        placeholder="Buscar por número o cliente..."
                        class="pl-8"
                        @keyup.enter="performSearch"
                    />
                </div>
                <Button @click="performSearch">Buscar</Button>
            </div>

            <!-- Sales Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Listado de Ventas</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Número</TableHead>
                                <TableHead>Cliente</TableHead>
                                <TableHead>Almacén</TableHead>
                                <TableHead>Total</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>SUNAT</TableHead>
                                <TableHead>Fecha</TableHead>
                                <TableHead class="text-right"
                                    >Acciones</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="sale in sales"
                                :key="sale.id"
                                class="cursor-pointer"
                                @click="openSale(sale)"
                            >
                                <TableCell class="font-medium">
                                    <a
                                        :href="`/sales/${sale.id}/edit`"
                                        class="hover:underline"
                                        @click.stop
                                    >
                                        {{ getDisplayNumber(sale) }}
                                    </a>
                                </TableCell>
                                <TableCell>{{
                                    getPartnerName(sale)
                                }}</TableCell>
                                <TableCell>{{ sale.warehouse.name }}</TableCell>
                                <TableCell class="font-mono">
                                    S/
                                    {{
                                        parseFloat(sale.total as any).toFixed(2)
                                    }}
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="
                                            getStatusBadge(sale.status).variant
                                        "
                                    >
                                        {{ getStatusBadge(sale.status).label }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="getSunatBadge(sale).variant"
                                    >
                                        {{ getSunatBadge(sale).label }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    {{
                                        new Date(
                                            sale.created_at,
                                        ).toLocaleDateString()
                                    }}
                                </TableCell>
                                <TableCell class="text-right">
                                    <div
                                        class="flex justify-end gap-2"
                                        @click.stop
                                    >
                                        <Button
                                            v-if="sale.status === 'draft'"
                                            variant="ghost"
                                            size="icon"
                                            @click="postSale(sale)"
                                            title="Publicar"
                                        >
                                            <CheckCircle class="h-4 w-4" />
                                        </Button>

                                        <Button
                                            v-if="sale.status === 'draft'"
                                            variant="ghost"
                                            size="icon"
                                            @click="openDeleteDialog(sale)"
                                            title="Eliminar"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <div
                        v-if="sales.length === 0"
                        class="py-8 text-center text-muted-foreground"
                    >
                        <span v-if="isLoading">Cargando...</span>
                        <span v-else>No se encontraron ventas</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Delete Confirmation Dialog -->
        <AlertDialog v-model:open="deleteDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>¿Estás seguro?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Esta acción eliminará la venta "{{
                            saleToDelete ? getDisplayNumber(saleToDelete) : ''
                        }}" permanentemente. Esta acción no se puede deshacer.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancelar</AlertDialogCancel>
                    <AlertDialogAction @click="deleteSale"
                        >Eliminar</AlertDialogAction
                    >
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
