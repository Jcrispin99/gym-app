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
import { Spinner } from '@/components/ui/spinner';
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
    Edit,
    MapPin,
    Plus,
    Search,
    Trash2,
    Warehouse as WarehouseIcon,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface Company {
    id: number;
    business_name?: string | null;
    trade_name?: string | null;
}

interface Warehouse {
    id: number;
    name: string;
    location: string | null;
    company: Company;
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Almacenes', href: '/warehouses' },
];

const warehouses = ref<Warehouse[]>([]);
const isLoading = ref(false);
const deleteDialogOpen = ref(false);
const warehouseToDelete = ref<Warehouse | null>(null);
const searchQuery = ref('');

const companyLabel = (company: Company) => {
    return (
        company.trade_name || company.business_name || `Empresa ${company.id}`
    );
};

const loadWarehouses = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/api/warehouses', {
            headers: { Accept: 'application/json' },
        });
        warehouses.value = (response.data?.data || []) as Warehouse[];
    } catch (e) {
        console.error('Error loading warehouses:', e);
        warehouses.value = [];
    } finally {
        isLoading.value = false;
    }
};

const openDeleteDialog = (warehouse: Warehouse) => {
    warehouseToDelete.value = warehouse;
    deleteDialogOpen.value = true;
};

const deleteWarehouse = async () => {
    const target = warehouseToDelete.value;
    if (!target) return;

    try {
        await axios.delete(`/api/warehouses/${target.id}`, {
            headers: { Accept: 'application/json' },
        });
        warehouses.value = warehouses.value.filter((w) => w.id !== target.id);
    } catch (e) {
        console.error('Error deleting warehouse:', e);
    } finally {
        deleteDialogOpen.value = false;
        warehouseToDelete.value = null;
    }
};

const filteredWarehouses = computed(() => {
    if (!searchQuery.value) {
        return warehouses.value;
    }

    const query = searchQuery.value.toLowerCase();
    return warehouses.value.filter(
        (warehouse) =>
            warehouse.name.toLowerCase().includes(query) ||
            warehouse.location?.toLowerCase().includes(query) ||
            companyLabel(warehouse.company).toLowerCase().includes(query),
    );
});

const uniqueCompanies = computed(() => {
    const companies = new Set(warehouses.value.map((w) => w.company.id));
    return companies.size;
});

const totalLocations = computed(() => {
    return warehouses.value.filter((w) => w.location).length;
});

onMounted(loadWarehouses);
</script>

<template>
    <Head title="Almacenes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <FormPageHeader
                title="Almacenes"
                description="Gestiona los almacenes donde se almacenará el inventario"
                :show-back="false"
            >
                <template #actions>
                    <Button @click="router.visit('/warehouses/create')">
                        <Plus class="mr-2 h-4 w-4" />
                        Nuevo Almacén
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
                            >Total Almacenes</CardTitle
                        >
                        <WarehouseIcon class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ warehouses.length }}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Empresas</CardTitle
                        >
                        <WarehouseIcon class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ uniqueCompanies }}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Con Ubicación</CardTitle
                        >
                        <MapPin class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ totalLocations }}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Search -->
            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <Search
                        class="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        placeholder="Buscar almacenes..."
                        class="pl-8"
                    />
                </div>
            </div>

            <!-- Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Listado de Almacenes</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Código</TableHead>
                                <TableHead>Ubicación</TableHead>
                                <TableHead>Empresa</TableHead>
                                <TableHead class="text-right"
                                    >Acciones</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="filteredWarehouses.length === 0">
                                <TableCell colspan="4" class="text-center">
                                    <span
                                        v-if="isLoading"
                                        class="inline-flex items-center gap-2"
                                    >
                                        <Spinner />
                                        Cargando...
                                    </span>
                                    <span v-else
                                        >No se encontraron almacenes</span
                                    >
                                </TableCell>
                            </TableRow>
                            <TableRow
                                v-for="warehouse in filteredWarehouses"
                                :key="warehouse.id"
                            >
                                <TableCell class="font-medium">{{
                                    warehouse.name
                                }}</TableCell>
                                <TableCell>
                                    <div
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <MapPin
                                            class="h-4 w-4 text-muted-foreground"
                                        />
                                        <span>{{
                                            warehouse.location ||
                                            'Sin ubicación'
                                        }}</span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge variant="secondary">
                                        {{ companyLabel(warehouse.company) }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="
                                                router.visit(
                                                    `/warehouses/${warehouse.id}/edit`,
                                                )
                                            "
                                        >
                                            <Edit class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="openDeleteDialog(warehouse)"
                                        >
                                            <Trash2
                                                class="h-4 w-4 text-destructive"
                                            />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>

        <!-- Delete Dialog -->
        <AlertDialog v-model:open="deleteDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>¿Estás seguro?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Esta acción no se puede deshacer. Se eliminará el
                        almacén
                        <strong>{{ warehouseToDelete?.name }}</strong
                        >.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancelar</AlertDialogCancel>
                    <AlertDialogAction @click="deleteWarehouse"
                        >Eliminar</AlertDialogAction
                    >
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
