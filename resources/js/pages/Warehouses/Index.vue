<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Head, router } from '@inertiajs/vue3';
import { Plus, Edit, Trash2, Warehouse as WarehouseIcon, Search, MapPin } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Company {
    id: number;
    name: string;
}

interface Warehouse {
    id: number;
    name: string;
    location: string | null;
    company: Company;
    created_at: string;
}

interface Props {
    warehouses: Warehouse[];
}

const props = defineProps<Props>();

const deleteDialogOpen = ref(false);
const warehouseToDelete = ref<Warehouse | null>(null);
const searchQuery = ref('');

const openDeleteDialog = (warehouse: Warehouse) => {
    warehouseToDelete.value = warehouse;
    deleteDialogOpen.value = true;
};

const deleteWarehouse = () => {
    if (warehouseToDelete.value) {
        router.delete(`/warehouses/${warehouseToDelete.value.id}`, {
            onSuccess: () => {
                deleteDialogOpen.value = false;
                warehouseToDelete.value = null;
            },
        });
    }
};

const filteredWarehouses = computed(() => {
    if (!searchQuery.value) {
        return props.warehouses;
    }

    const query = searchQuery.value.toLowerCase();
    return props.warehouses.filter((warehouse) =>
        warehouse.name.toLowerCase().includes(query) ||
        warehouse.location?.toLowerCase().includes(query) ||
        warehouse.company.name.toLowerCase().includes(query)
    );
});

const uniqueCompanies = computed(() => {
    const companies = new Set(props.warehouses.map((w) => w.company.id));
    return companies.size;
});

const totalLocations = computed(() => {
    return props.warehouses.filter((w) => w.location).length;
});
</script>

<template>
    <Head title="Almacenes" />

    <AppLayout>
        <div class="flex flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Almacenes</h1>
                    <p class="text-muted-foreground">
                        Gestiona los almacenes donde se almacenará el inventario
                    </p>
                </div>
                <Button @click="router.visit('/warehouses/create')">
                    <Plus class="mr-2 h-4 w-4" />
                    Nuevo Almacén
                </Button>
            </div>

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Almacenes</CardTitle>
                        <WarehouseIcon class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ warehouses.length }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Empresas</CardTitle>
                        <WarehouseIcon class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ uniqueCompanies }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Con Ubicación</CardTitle>
                        <MapPin class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ totalLocations }}</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Search -->
            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input v-model="searchQuery" placeholder="Buscar almacenes..." class="pl-8" />
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
                                <TableHead class="text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="filteredWarehouses.length === 0">
                                <TableCell colspan="4" class="text-center">
                                    No se encontraron almacenes
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="warehouse in filteredWarehouses" :key="warehouse.id">
                                <TableCell class="font-medium">{{ warehouse.name }}</TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-2 text-sm">
                                        <MapPin class="h-4 w-4 text-muted-foreground" />
                                        <span>{{ warehouse.location || 'Sin ubicación' }}</span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge variant="secondary">
                                        {{ warehouse.company.name }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="
                                                router.visit(`/warehouses/${warehouse.id}/edit`)
                                            "
                                        >
                                            <Edit class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="openDeleteDialog(warehouse)"
                                        >
                                            <Trash2 class="h-4 w-4 text-destructive" />
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
                        Esta acción no se puede deshacer. Se eliminará el almacén
                        <strong>{{ warehouseToDelete?.name }}</strong>.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancelar</AlertDialogCancel>
                    <AlertDialogAction @click="deleteWarehouse">Eliminar</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
