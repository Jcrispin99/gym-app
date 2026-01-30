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
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectTrigger } from '@/components/ui/select';
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
import { Edit, Filter, Search, Trash2, UserPlus, Users } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface Company {
    id: number;
    trade_name: string;
}

interface Supplier {
    id: number;
    document_number: string;
    business_name: string | null;
    first_name: string | null;
    last_name: string | null;
    email: string | null;
    phone: string | null;
    status: string;
    company?: Company;
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Proveedores', href: '/suppliers' },
];

const suppliers = ref<Supplier[]>([]);
const isLoading = ref(false);
const deleteDialogOpen = ref(false);
const supplierToDelete = ref<Supplier | null>(null);
const deleteError = ref<string | null>(null);

// Search and multi-select filters
const searchQuery = ref('');
const selectedStatuses = ref<string[]>([]);

const headers = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

const loadSuppliers = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/api/suppliers', { headers });
        suppliers.value = (response.data?.data || []) as Supplier[];
    } catch (e) {
        console.error('Error loading suppliers:', e);
        suppliers.value = [];
    } finally {
        isLoading.value = false;
    }
};

const openDeleteDialog = (supplier: Supplier) => {
    supplierToDelete.value = supplier;
    deleteError.value = null;
    deleteDialogOpen.value = true;
};

const deleteSupplier = async () => {
    const target = supplierToDelete.value;
    if (!target) return;

    deleteError.value = null;
    try {
        await axios.delete(`/api/suppliers/${target.id}`, { headers });
        suppliers.value = suppliers.value.filter((s) => s.id !== target.id);
        deleteDialogOpen.value = false;
        supplierToDelete.value = null;
    } catch (e: any) {
        if (e?.response?.status === 422) {
            const errors = e.response.data?.errors || {};
            deleteError.value =
                errors.supplier?.[0] || errors.status?.[0] || 'No se pudo eliminar.';
        } else {
            console.error('Error deleting supplier:', e);
            deleteError.value = 'No se pudo eliminar.';
        }
    }
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('es-PE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const getStatusBadge = (status: string) => {
    const badges: Record<string, string> = {
        active: 'bg-green-100 text-green-800',
        inactive: 'bg-gray-100 text-gray-800',
        suspended: 'bg-red-100 text-red-800',
    };
    return badges[status] || badges.active;
};

const getSupplierName = (supplier: Supplier) => {
    if (supplier.business_name) {
        return supplier.business_name;
    }
    return `${supplier.first_name} ${supplier.last_name}`;
};

// Filtered suppliers
const filteredSuppliers = computed(() => {
    let filtered = suppliers.value;

    // Search filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter((supplier) => {
            const name = getSupplierName(supplier).toLowerCase();
            return (
                name.includes(query) ||
                supplier.document_number.toLowerCase().includes(query) ||
                supplier.email?.toLowerCase().includes(query)
            );
        });
    }

    // Status filter
    if (selectedStatuses.value.length > 0) {
        filtered = filtered.filter((p) =>
            selectedStatuses.value.includes(p.status),
        );
    }

    return filtered;
});

const activeFiltersCount = computed(() => {
    return selectedStatuses.value.length;
});

onMounted(loadSuppliers);
</script>

<template>
    <Head title="Proveedores" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <FormPageHeader
                title="Proveedores"
                description="Gestiona los proveedores de la empresa"
                :show-back="false"
            >
                <template #actions>
                    <Button @click="router.visit('/suppliers/create')">
                        <UserPlus class="mr-2 h-4 w-4" />
                        Nuevo Proveedor
                    </Button>
                </template>
            </FormPageHeader>

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">
                            Total Proveedores
                        </CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ filteredSuppliers.length }}
                        </div>
                        <p class="text-xs text-muted-foreground">Registrados</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">
                            Activos
                        </CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{
                                filteredSuppliers.filter(
                                    (p) => p.status === 'active',
                                ).length
                            }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Proveedores activos
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">
                            Suspendidos
                        </CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{
                                filteredSuppliers.filter(
                                    (p) => p.status === 'suspended',
                                ).length
                            }}
                        </div>
                        <p class="text-xs text-muted-foreground">Suspendidos</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Suppliers Table -->
            <Card>
                <CardHeader>
                    <div class="flex items-start justify-between gap-4">
                        <!-- Title + Description (Left) -->
                        <div>
                            <CardTitle>Listado de Proveedores</CardTitle>
                            <CardDescription>
                                Mostrando {{ filteredSuppliers.length }} de
                                {{ suppliers.length }} proveedores
                            </CardDescription>
                        </div>

                        <!-- Search + Filter (Right) -->
                        <div class="flex gap-2">
                            <!-- Search Bar -->
                            <div class="relative w-[300px]">
                                <Search
                                    class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    v-model="searchQuery"
                                    placeholder="Buscar por nombre, documento..."
                                    class="pl-10"
                                />
                            </div>

                            <!-- Multi-Select Filter -->
                            <Select>
                                <SelectTrigger class="w-[180px]">
                                    <div class="flex items-center gap-2">
                                        <Filter class="h-4 w-4" />
                                        <span>Filtros</span>
                                        <Badge
                                            v-if="activeFiltersCount > 0"
                                            variant="secondary"
                                            class="ml-auto h-5 px-1.5"
                                        >
                                            {{ activeFiltersCount }}
                                        </Badge>
                                    </div>
                                </SelectTrigger>
                                <SelectContent class="w-[220px]">
                                    <!-- Status Filters -->
                                    <div class="px-2 py-1.5">
                                        <p
                                            class="mb-2 text-xs font-semibold text-muted-foreground"
                                        >
                                            Estado
                                        </p>
                                        <div class="space-y-2">
                                            <label
                                                class="flex cursor-pointer items-center gap-2"
                                            >
                                                <Checkbox
                                                    :checked="
                                                        selectedStatuses.includes(
                                                            'active',
                                                        )
                                                    "
                                                    @update:checked="
                                                        (checked: boolean) => {
                                                            selectedStatuses =
                                                                checked
                                                                    ? [
                                                                          ...selectedStatuses,
                                                                          'active',
                                                                      ]
                                                                    : selectedStatuses.filter(
                                                                          (s) =>
                                                                              s !==
                                                                              'active',
                                                                      );
                                                        }
                                                    "
                                                />
                                                <span class="text-sm"
                                                    >Activos</span
                                                >
                                            </label>
                                            <label
                                                class="flex cursor-pointer items-center gap-2"
                                            >
                                                <Checkbox
                                                    :checked="
                                                        selectedStatuses.includes(
                                                            'inactive',
                                                        )
                                                    "
                                                    @update:checked="
                                                        (checked: boolean) => {
                                                            selectedStatuses =
                                                                checked
                                                                    ? [
                                                                          ...selectedStatuses,
                                                                          'inactive',
                                                                      ]
                                                                    : selectedStatuses.filter(
                                                                          (s) =>
                                                                              s !==
                                                                              'inactive',
                                                                      );
                                                        }
                                                    "
                                                />
                                                <span class="text-sm"
                                                    >Inactivos</span
                                                >
                                            </label>
                                            <label
                                                class="flex cursor-pointer items-center gap-2"
                                            >
                                                <Checkbox
                                                    :checked="
                                                        selectedStatuses.includes(
                                                            'suspended',
                                                        )
                                                    "
                                                    @update:checked="
                                                        (checked: boolean) => {
                                                            selectedStatuses =
                                                                checked
                                                                    ? [
                                                                          ...selectedStatuses,
                                                                          'suspended',
                                                                      ]
                                                                    : selectedStatuses.filter(
                                                                          (s) =>
                                                                              s !==
                                                                              'suspended',
                                                                      );
                                                        }
                                                    "
                                                />
                                                <span class="text-sm"
                                                    >Suspendidos</span
                                                >
                                            </label>
                                        </div>
                                    </div>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Table v-if="filteredSuppliers.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Documento</TableHead>
                                <TableHead>Nombre / Razón Social</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Teléfono</TableHead>
                                <TableHead>Compañía</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>Registro</TableHead>
                                <TableHead class="text-right"
                                    >Acciones</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="supplier in filteredSuppliers"
                                :key="supplier.id"
                            >
                                <TableCell class="font-medium">
                                    {{ supplier.document_number }}
                                </TableCell>
                                <TableCell>
                                    {{ getSupplierName(supplier) }}
                                </TableCell>
                                <TableCell>
                                    {{ supplier.email || '-' }}
                                </TableCell>
                                <TableCell>
                                    {{ supplier.phone || '-' }}
                                </TableCell>
                                <TableCell>
                                    {{ supplier.company?.trade_name || '-' }}
                                </TableCell>
                                <TableCell>
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="getStatusBadge(supplier.status)"
                                    >
                                        {{ supplier.status }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    {{ formatDate(supplier.created_at) }}
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="
                                                router.visit(
                                                    `/suppliers/${supplier.id}/edit`,
                                                )
                                            "
                                        >
                                            <Edit class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="openDeleteDialog(supplier)"
                                        >
                                            <Trash2
                                                class="h-4 w-4 text-red-500"
                                            />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <div v-else class="py-10 text-center text-muted-foreground">
                        <span v-if="isLoading">Cargando...</span>
                        <span v-else>No hay proveedores registrados</span>
                    </div>
                </CardContent>
            </Card>

            <!-- Delete Confirmation Dialog -->
            <AlertDialog v-model:open="deleteDialogOpen">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>¿Estás seguro?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Esta acción eliminará permanentemente al proveedor
                            <strong v-if="supplierToDelete">
                                {{ getSupplierName(supplierToDelete) }} </strong
                            >. Esta acción no se puede deshacer.
                        </AlertDialogDescription>
                        <AlertDialogDescription v-if="deleteError" class="text-destructive">
                            {{ deleteError }}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction @click="deleteSupplier">
                            Eliminar
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    </AppLayout>
</template>
