<script setup lang="ts">
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Building2, MapPin, Phone, Mail, Plus, Pencil, Trash2 } from 'lucide-vue-next';

interface Company {
    id: number;
    business_name: string;
    trade_name: string;
    ruc: string;
    address: string | null;
    phone: string | null;
    email: string | null;
    branch_code: string | null;
    district: string | null;
    department: string | null;
    province: string | null;
    ubigeo: string | null;
    urbanization: string | null;
    parent_id: number | null;
    active: boolean;
    is_main_office: boolean;
}

interface Props {
    main_office: Company | null;
    branches: Company[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Compañías',
        href: '/companies',
    },
];

const allCompanies = computed(() => {
    const companies: Company[] = [];
    if (props.main_office) {
        companies.push(props.main_office);
    }
    companies.push(...props.branches);
    return companies;
});

// State for delete operation only
const deleteDialogOpen = ref(false);
const selectedCompany = ref<Company | null>(null);

const openDeleteDialog = (company: Company) => {
    selectedCompany.value = company;
    deleteDialogOpen.value = true;
};

const deleteCompany = () => {
    if (selectedCompany.value) {
        router.delete(`/companies/${selectedCompany.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                deleteDialogOpen.value = false;
                selectedCompany.value = null;
            },
        });
    }
};

</script>

<template>
    <Head title="Compañías" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Compañías</h1>
                    <p class="text-muted-foreground">
                        Gestiona tu casa matriz y sucursales
                    </p>
                </div>
                <Button @click="router.visit('/companies/create')">
                    <Plus class="mr-2 h-4 w-4" />
                    Nueva Sucursal
                </Button>
            </div>

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">
                            Casa Matriz
                        </CardTitle>
                        <Building2 class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">1</div>
                        <p class="text-xs text-muted-foreground">
                            {{ main_office?.trade_name || 'No configurada' }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">
                            Sucursales
                        </CardTitle>
                        <Building2 class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ branches.length }}</div>
                        <p class="text-xs text-muted-foreground">
                            Activas
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">
                            Total
                        </CardTitle>
                        <Building2 class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ allCompanies.length }}</div>
                        <p class="text-xs text-muted-foreground">
                            Compañías totales
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Companies Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Listado de Compañías</CardTitle>
                    <CardDescription>
                        Todas las compañías y sucursales de tu organización
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Tipo</TableHead>
                                <TableHead>Nombre</TableHead>
                                <TableHead>RUC</TableHead>
                                <TableHead>Código</TableHead>
                                <TableHead>Ubicación</TableHead>
                                <TableHead>Contacto</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead class="text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="allCompanies.length === 0">
                                <TableCell colspan="8" class="text-center text-muted-foreground">
                                    No hay compañías registradas
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="company in allCompanies" :key="company.id">
                                <!-- Tipo -->
                                <TableCell>
                                    <Badge :variant="company.is_main_office ? 'default' : 'secondary'">
                                        {{ company.is_main_office ? 'Matriz' : 'Sucursal' }}
                                    </Badge>
                                </TableCell>

                                <!-- Nombre -->
                                <TableCell>
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ company.trade_name }}</span>
                                        <span class="text-xs text-muted-foreground">
                                            {{ company.business_name }}
                                        </span>
                                    </div>
                                </TableCell>

                                <!-- RUC -->
                                <TableCell>
                                    <span class="font-mono text-sm">{{ company.ruc }}</span>
                                </TableCell>

                                <!-- Código -->
                                <TableCell>
                                    <Badge v-if="company.branch_code" variant="outline">
                                        {{ company.branch_code }}
                                    </Badge>
                                    <span v-else class="text-muted-foreground">-</span>
                                </TableCell>

                                <!-- Ubicación -->
                                <TableCell>
                                    <div class="flex items-start gap-1">
                                        <MapPin class="h-3 w-3 mt-1 text-muted-foreground" />
                                        <div class="flex flex-col text-sm">
                                            <span>{{ company.district }}</span>
                                            <span class="text-xs text-muted-foreground">
                                                {{ company.province }}, {{ company.department }}
                                            </span>
                                        </div>
                                    </div>
                                </TableCell>

                                <!-- Contacto -->
                                <TableCell>
                                    <div class="flex flex-col gap-1 text-sm">
                                        <div v-if="company.phone" class="flex items-center gap-1">
                                            <Phone class="h-3 w-3 text-muted-foreground" />
                                            <span>{{ company.phone }}</span>
                                        </div>
                                        <div v-if="company.email" class="flex items-center gap-1">
                                            <Mail class="h-3 w-3 text-muted-foreground" />
                                            <span class="text-xs">{{ company.email }}</span>
                                        </div>
                                    </div>
                                </TableCell>

                                <!-- Estado -->
                                <TableCell>
                                    <Badge :variant="company.active ? 'default' : 'destructive'">
                                        {{ company.active ? 'Activa' : 'Inactiva' }}
                                    </Badge>
                                </TableCell>

                                <!-- Acciones -->
                                <TableCell class="text-right">
                                    <div class="flex gap-2 justify-end">
                                        <Button 
                                            variant="ghost" 
                                            size="sm"
                                            @click="router.visit(`/companies/${company.id}/edit`)"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button 
                                            variant="ghost" 
                                            size="sm"
                                            @click="openDeleteDialog(company)"
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

            <!-- Delete Confirmation Dialog -->
            <AlertDialog v-model:open="deleteDialogOpen">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>¿Estás seguro?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Esta acción no se puede deshacer. Se eliminará permanentemente
                            <strong>{{ selectedCompany?.trade_name }}</strong>.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction @click="deleteCompany">
                            Eliminar
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    </AppLayout>
</template>
