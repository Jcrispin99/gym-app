<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
import {
    Select,
    SelectContent,
    SelectTrigger,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import { Head, router } from '@inertiajs/vue3';
import { UserPlus, Edit, Trash2, Users, Search, Filter } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import type { BreadcrumbItem } from '@/types';

interface Company {
    id: number;
    trade_name: string;
}

interface Customer {
    id: number;
    document_number: string;
    first_name: string;
    last_name: string;
    email: string | null;
    phone: string | null;
    status: string;
    user_id: number | null;
    company?: Company;
    created_at: string;
}

interface Props {
    customers: Customer[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Clientes', href: '/customers' },
];

const deleteDialogOpen = ref(false);
const customerToDelete = ref<Customer | null>(null);

// Search and multi-select filters
const searchQuery = ref('');
const selectedStatuses = ref<string[]>([]);
const selectedPortalFilters = ref<string[]>([]);

const openDeleteDialog = (customer: Customer) => {
    customerToDelete.value = customer;
    deleteDialogOpen.value = true;
};

const deleteCustomer = () => {
    if (customerToDelete.value) {
        router.delete(`/customers/${customerToDelete.value.id}`, {
            onSuccess: () => {
                deleteDialogOpen.value = false;
                customerToDelete.value = null;
            },
        });
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

// Filtered customers with multi-select checkboxes
const filteredCustomers = computed(() => {
    let filtered = props.customers;

    // Search filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(customer =>
            customer.first_name.toLowerCase().includes(query) ||
            customer.last_name.toLowerCase().includes(query) ||
            customer.document_number.toLowerCase().includes(query) ||
            customer.email?.toLowerCase().includes(query)
        );
    }

    // Status filter (multiple selection)
    if (selectedStatuses.value.length > 0) {
        filtered = filtered.filter(m => selectedStatuses.value.includes(m.status));
    }

    // Portal access filter (multiple selection)
    if (selectedPortalFilters.value.length > 0) {
        let portalFiltered: Customer[] = [];
        if (selectedPortalFilters.value.includes('with_portal')) {
            portalFiltered = [...portalFiltered, ...filtered.filter(m => m.user_id !== null)];
        }
        if (selectedPortalFilters.value.includes('without_portal')) {
            portalFiltered = [...portalFiltered, ...filtered.filter(m => m.user_id === null)];
        }
        // Remove duplicates
        filtered = portalFiltered.filter((item, index, self) =>
            index === self.findIndex((t) => t.id === item.id)
        );
    }

    return filtered;
});

const activeFiltersCount = computed(() => {
    return selectedStatuses.value.length + selectedPortalFilters.value.length;
});
</script>

<template>
    <Head title="Clientes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Clientes</h1>
                    <p class="text-muted-foreground">
                        Gestiona los clientes del gimnasio
                    </p>
                </div>
                <Button @click="router.visit('/customers/create')">
                    <UserPlus class="mr-2 h-4 w-4" />
                    Nuevo Cliente
                </Button>
            </div>

            <!-- Stats Cards - Compact (3 cards) -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">
                            Total Clientes
                        </CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ filteredCustomers.length }}</div>
                        <p class="text-xs text-muted-foreground">
                            Registrados
                        </p>
                    </CardContent>
                </Card>
                
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">
                            Activos
                        </CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ filteredCustomers.filter(m => m.status === 'active').length }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            clientes activos
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">
                            Suspendidos
                        </CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ filteredCustomers.filter(m => m.status === 'suspended').length }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Suspendidos
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Customers Table -->
            <Card>
                <CardHeader>
                    <div class="flex items-start justify-between gap-4">
                        <!-- Title + Description (Left) -->
                        <div>
                            <CardTitle>Listado de Clientes</CardTitle>
                            <CardDescription>
                                Mostrando {{ filteredCustomers.length }} de {{ customers.length }} clientes
                            </CardDescription>
                        </div>


                        <!-- Search + Filter (Right) -->
                        <div class="flex gap-2">
                            <!-- Search Bar -->
                            <div class="relative w-[300px]">
                                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    v-model="searchQuery"
                                    placeholder="Buscar..."
                                    class="pl-10"
                                />
                            </div>

                            <!-- Multi-Select Filter -->
                            <Select>
                                <SelectTrigger class="w-[180px]">
                                    <div class="flex items-center gap-2">
                                        <Filter class="h-4 w-4" />
                                        <span>Filtros</span>
                                        <Badge v-if="activeFiltersCount > 0" variant="secondary" class="ml-auto h-5 px-1.5">
                                            {{ activeFiltersCount }}
                                        </Badge>
                                    </div>
                                </SelectTrigger>
                                <SelectContent class="w-[220px]">
                                    <!-- Status Filters -->
                                    <div class="px-2 py-1.5">
                                        <p class="text-xs font-semibold text-muted-foreground mb-2">Estado</p>
                                        <div class="space-y-2">
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <Checkbox
                                                    :checked="selectedStatuses.includes('active')"
                                                    @update:checked="(checked: any) => {
                                                        selectedStatuses = checked 
                                                            ? [...selectedStatuses, 'active']
                                                            : selectedStatuses.filter(s => s !== 'active');
                                                    }"
                                                />
                                                <span class="text-sm">Activos</span>
                                            </label>
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <Checkbox
                                                    :checked="selectedStatuses.includes('inactive')"
                                                    @update:checked="(checked: any) => {
                                                        selectedStatuses = checked 
                                                            ? [...selectedStatuses, 'inactive']
                                                            : selectedStatuses.filter(s => s !== 'inactive');
                                                    }"
                                                />
                                                <span class="text-sm">Inactivos</span>
                                            </label>
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <Checkbox
                                                    :checked="selectedStatuses.includes('suspended')"
                                                    @update:checked="(checked: any) => {
                                                        selectedStatuses = checked 
                                                            ? [...selectedStatuses, 'suspended']
                                                            : selectedStatuses.filter(s => s !== 'suspended');
                                                    }"
                                                />
                                                <span class="text-sm">Suspendidos</span>
                                            </label>
                                        </div>
                                    </div>


                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Table v-if="filteredCustomers.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Documento</TableHead>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Teléfono</TableHead>
                                <TableHead>Compañía</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>Registro</TableHead>
                                <TableHead class="text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="customer in filteredCustomers" :key="customer.id">
                                <TableCell class="font-medium">
                                    {{ customer.document_number }}
                                </TableCell>
                                <TableCell>
                                    {{ customer.first_name }} {{ customer.last_name }}
                                </TableCell>
                                <TableCell>
                                    {{ customer.email || '-' }}
                                </TableCell>
                                <TableCell>
                                    {{ customer.phone || '-' }}
                                </TableCell>
                                <TableCell>
                                    {{ customer.company?.trade_name || '-' }}
                                </TableCell>
                                <TableCell>
                                    <span 
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="getStatusBadge(customer.status)"
                                    >
                                        {{ customer.status }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    {{ formatDate(customer.created_at) }}
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="router.visit(`/customers/${customer.id}/edit`)"
                                        >
                                            <Edit class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="openDeleteDialog(customer)"
                                        >
                                            <Trash2 class="h-4 w-4 text-red-500" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <div v-else class="py-10 text-center text-muted-foreground">
                        No hay clientes registrados
                    </div>
                </CardContent>
            </Card>

            <!-- Delete Confirmation Dialog -->
            <AlertDialog v-model:open="deleteDialogOpen">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>¿Estás seguro?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Esta acción eliminará permanentemente al cliente
                            <strong v-if="customerToDelete">
                                {{ customerToDelete.first_name }} {{ customerToDelete.last_name }}
                            </strong>.
                            Esta acción no se puede deshacer.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction @click="deleteCustomer">
                            Eliminar
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    </AppLayout>
</template>
