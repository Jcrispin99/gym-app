<script setup lang="ts">
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
import {
    CheckCircle,
    Edit,
    Plus,
    Send,
    ShoppingCart,
    Trash2,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

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

interface Props {
    sales: {
        data: Sale[];
    };
    filters?: {
        search?: string;
        status?: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Ventas', href: '/sales' },
];

const deleteDialogOpen = ref(false);
const saleToDelete = ref<Sale | null>(null);
const searchQuery = ref(props.filters?.search || '');

const openDeleteDialog = (sale: Sale) => {
    saleToDelete.value = sale;
    deleteDialogOpen.value = true;
};

const deleteSale = () => {
    if (saleToDelete.value) {
        router.delete(`/sales/${saleToDelete.value.id}`, {
            onSuccess: () => {
                deleteDialogOpen.value = false;
                saleToDelete.value = null;
            },
        });
    }
};

const postSale = (sale: Sale) => {
    if (
        confirm(
            `¿Estás seguro de publicar esta venta? Se asignará el número y se reducirá el inventario.`,
        )
    ) {
        router.post(
            `/sales/${sale.id}/post`,
            {},
            {
                preserveScroll: true,
            },
        );
    }
};

const cancelSale = (sale: Sale) => {
    if (
        confirm(
            `¿Estás seguro de cancelar esta venta? Se devolverá el stock al inventario.`,
        )
    ) {
        router.post(
            `/sales/${sale.id}/cancel`,
            {},
            {
                preserveScroll: true,
            },
        );
    }
};

const performSearch = () => {
    router.get(
        '/sales',
        { search: searchQuery.value },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const totalSales = computed(() => props.sales.data.length);
const draftSales = computed(
    () => props.sales.data.filter((s) => s.status === 'draft').length,
);
const postedSales = computed(
    () => props.sales.data.filter((s) => s.status === 'posted').length,
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

const retrySunat = (sale: Sale) => {
    router.post(`/sales/${sale.id}/sunat/retry`, {}, { preserveScroll: true });
};

const canSendSunat = (sale: Sale) => {
    if (sale.status !== 'posted') return false;
    if (sale.sunat_response?.accepted === true) return false;
    return sale.sunat_status !== 'accepted';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Ventas" />

        <div class="flex flex-col gap-4 p-4">
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
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Ventas</CardTitle>
                    <Button as-child>
                        <a href="/sales/create">
                            <Plus class="mr-2 h-4 w-4" />
                            Nueva Venta
                        </a>
                    </Button>
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
                            <TableRow v-for="sale in sales.data" :key="sale.id">
                                <TableCell class="font-medium">
                                    {{ getDisplayNumber(sale) }}
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
                                    <div class="flex justify-end gap-2">
                                        <!-- Editar (solo borradores) -->
                                        <Button
                                            v-if="sale.status === 'draft'"
                                            variant="ghost"
                                            size="icon"
                                            as-child
                                            title="Editar"
                                        >
                                            <a :href="`/sales/${sale.id}/edit`">
                                                <Edit class="h-4 w-4" />
                                            </a>
                                        </Button>

                                        <!-- Publicar (solo borradores) -->
                                        <Button
                                            v-if="sale.status === 'draft'"
                                            variant="ghost"
                                            size="icon"
                                            @click="postSale(sale)"
                                            title="Publicar"
                                        >
                                            <CheckCircle class="h-4 w-4" />
                                        </Button>

                                        <!-- Cancelar (solo publicadas) -->
                                        <Button
                                            v-if="sale.status === 'posted'"
                                            variant="ghost"
                                            size="icon"
                                            @click="cancelSale(sale)"
                                            title="Cancelar"
                                        >
                                            <XCircle class="h-4 w-4" />
                                        </Button>

                                        <Button
                                            v-if="canSendSunat(sale)"
                                            variant="ghost"
                                            size="icon"
                                            @click="retrySunat(sale)"
                                            title="Enviar a SUNAT"
                                        >
                                            <Send class="h-4 w-4" />
                                        </Button>

                                        <!-- Eliminar (solo borradores) -->
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
                        v-if="sales.data.length === 0"
                        class="py-8 text-center text-muted-foreground"
                    >
                        No se encontraron ventas
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
