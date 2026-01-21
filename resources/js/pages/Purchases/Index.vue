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
import {
    CheckCircle,
    Edit,
    Plus,
    Search,
    ShoppingCart,
    Trash2,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Purchase {
    id: number;
    serie: string | null;
    correlative: string | null;
    status: 'draft' | 'posted' | 'cancelled';
    payment_status: string;
    total: number;
    partner: {
        id: number;
        name: string;
    };
    warehouse: {
        id: number;
        name: string;
    };
    created_at: string;
}

interface Props {
    purchases: {
        data: Purchase[];
    };
    filters?: {
        search?: string;
        status?: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Compras', href: '/purchases' },
];

const deleteDialogOpen = ref(false);
const purchaseToDelete = ref<Purchase | null>(null);
const searchQuery = ref(props.filters?.search || '');

const openDeleteDialog = (purchase: Purchase) => {
    purchaseToDelete.value = purchase;
    deleteDialogOpen.value = true;
};

const deletePurchase = () => {
    if (purchaseToDelete.value) {
        router.delete(`/purchases/${purchaseToDelete.value.id}`, {
            onSuccess: () => {
                deleteDialogOpen.value = false;
                purchaseToDelete.value = null;
            },
        });
    }
};

const postPurchase = (purchase: Purchase) => {
    if (
        confirm(
            `¿Estás seguro de publicar esta compra? Se generará la numeración y se actualizará el inventario.`,
        )
    ) {
        router.post(
            `/purchases/${purchase.id}/post`,
            {},
            {
                preserveScroll: true,
            },
        );
    }
};

const cancelPurchase = (purchase: Purchase) => {
    if (
        confirm(
            `¿Estás seguro de cancelar esta compra? Se revertirá el inventario.`,
        )
    ) {
        router.post(
            `/purchases/${purchase.id}/cancel`,
            {},
            {
                preserveScroll: true,
            },
        );
    }
};

const performSearch = () => {
    router.get(
        '/purchases',
        { search: searchQuery.value },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const totalPurchases = computed(() => props.purchases.data.length);
const draftPurchases = computed(
    () => props.purchases.data.filter((p) => p.status === 'draft').length,
);
const postedPurchases = computed(
    () => props.purchases.data.filter((p) => p.status === 'posted').length,
);

const getStatusBadge = (status: string) => {
    const badges: Record<string, any> = {
        draft: { label: 'Borrador', variant: 'secondary' },
        posted: { label: 'Publicado', variant: 'default' },
        cancelled: { label: 'Cancelado', variant: 'destructive' },
    };
    return badges[status] || { label: status, variant: 'outline' };
};

const getDisplayNumber = (purchase: Purchase) => {
    if (purchase.serie && purchase.correlative) {
        return `${purchase.serie}-${purchase.correlative}`;
    }
    return `Borrador #${purchase.id}`;
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Compras" />

        <div class="flex flex-col gap-4 p-4">
            <FormPageHeader
                title="Compras"
                description="Gestiona compras y borradores"
                :show-back="false"
            >
                <template #actions>
                    <Button @click="router.visit('/purchases/create')">
                        <Plus class="mr-2 h-4 w-4" />
                        Nueva Compra
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
                            >Total Compras</CardTitle
                        >
                        <ShoppingCart class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ totalPurchases }}
                        </div>
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
                        <div class="text-2xl font-bold">
                            {{ draftPurchases }}
                        </div>
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
                        <div class="text-2xl font-bold">
                            {{ postedPurchases }}
                        </div>
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
                        placeholder="Buscar por número o proveedor..."
                        class="pl-8"
                        @keyup.enter="performSearch"
                    />
                </div>
                <Button @click="performSearch">Buscar</Button>
            </div>

            <!-- Purchases Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Listado de Compras</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Número</TableHead>
                                <TableHead>Proveedor</TableHead>
                                <TableHead>Almacén</TableHead>
                                <TableHead>Total</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>Fecha</TableHead>
                                <TableHead class="text-right"
                                    >Acciones</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="purchase in purchases.data"
                                :key="purchase.id"
                            >
                                <TableCell class="font-medium">
                                    {{ getDisplayNumber(purchase) }}
                                </TableCell>
                                <TableCell>{{
                                    purchase.partner.name
                                }}</TableCell>
                                <TableCell>{{
                                    purchase.warehouse.name
                                }}</TableCell>
                                <TableCell class="font-mono">
                                    S/
                                    {{
                                        parseFloat(
                                            purchase.total as any,
                                        ).toFixed(2)
                                    }}
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="
                                            getStatusBadge(purchase.status)
                                                .variant
                                        "
                                    >
                                        {{
                                            getStatusBadge(purchase.status)
                                                .label
                                        }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    {{
                                        new Date(
                                            purchase.created_at,
                                        ).toLocaleDateString()
                                    }}
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <!-- Editar (solo borradores) -->
                                        <Button
                                            v-if="purchase.status === 'draft'"
                                            variant="ghost"
                                            size="icon"
                                            as-child
                                            title="Editar"
                                        >
                                            <a
                                                :href="`/purchases/${purchase.id}/edit`"
                                            >
                                                <Edit class="h-4 w-4" />
                                            </a>
                                        </Button>

                                        <!-- Publicar (solo borradores) -->
                                        <Button
                                            v-if="purchase.status === 'draft'"
                                            variant="ghost"
                                            size="icon"
                                            @click="postPurchase(purchase)"
                                            title="Publicar"
                                        >
                                            <CheckCircle class="h-4 w-4" />
                                        </Button>

                                        <!-- Cancelar (solo publicadas) -->
                                        <Button
                                            v-if="purchase.status === 'posted'"
                                            variant="ghost"
                                            size="icon"
                                            @click="cancelPurchase(purchase)"
                                            title="Cancelar"
                                        >
                                            <XCircle class="h-4 w-4" />
                                        </Button>

                                        <!-- Eliminar (solo borradores) -->
                                        <Button
                                            v-if="purchase.status === 'draft'"
                                            variant="ghost"
                                            size="icon"
                                            @click="openDeleteDialog(purchase)"
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
                        v-if="purchases.data.length === 0"
                        class="py-8 text-center text-muted-foreground"
                    >
                        No se encontraron compras
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
                        Esta acción eliminará la compra "{{
                            purchaseToDelete
                                ? getDisplayNumber(purchaseToDelete)
                                : ''
                        }}" permanentemente. Esta acción no se puede deshacer.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancelar</AlertDialogCancel>
                    <AlertDialogAction @click="deletePurchase"
                        >Eliminar</AlertDialogAction
                    >
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
