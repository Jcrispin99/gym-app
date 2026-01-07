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
import { Plus, Edit, Trash2, Percent, Search, Power } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import type { BreadcrumbItem } from '@/types';

interface Tax {
    id: number;
    name: string;
    description: string | null;
    invoice_label: string | null;
    tax_type: string;
    affectation_type_code: string | null;
    rate_percent: number;
    is_price_inclusive: boolean;
    is_active: boolean;
    is_default: boolean;
    created_at: string;
}

interface Props {
    taxes: Tax[];
    filters?: {
        search?: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Impuestos', href: '/taxes' },
];

const deleteDialogOpen = ref(false);
const taxToDelete = ref<Tax | null>(null);
const searchQuery = ref(props.filters?.search || '');

const openDeleteDialog = (tax: Tax) => {
    taxToDelete.value = tax;
    deleteDialogOpen.value = true;
};

const deleteTax = () => {
    if (taxToDelete.value) {
        router.delete(`/taxes/${taxToDelete.value.id}`, {
            onSuccess: () => {
                deleteDialogOpen.value = false;
                taxToDelete.value = null;
            },
        });
    }
};

const toggleStatus = (tax: Tax) => {
    router.post(`/taxes/${tax.id}/toggle-status`, {}, {
        preserveScroll: true,
    });
};

const performSearch = () => {
    router.get('/taxes', { search: searchQuery.value }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const totalTaxes = computed(() => props.taxes.length);
const activeTaxes = computed(() => props.taxes.filter(t => t.is_active).length);
const igvTaxes = computed(() => props.taxes.filter(t => t.tax_type === 'IGV').length);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Impuestos" />

        <div class="flex flex-col gap-4 p-4">
            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Impuestos</CardTitle>
                        <Percent class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ totalTaxes }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Impuestos Activos</CardTitle>
                        <Percent class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ activeTaxes }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Impuestos IGV</CardTitle>
                        <Percent class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ igvTaxes }}</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Search Bar -->
            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input 
                        v-model="searchQuery"
                        placeholder="Buscar por nombre o tipo..." 
                        class="pl-8"
                        @keyup.enter="performSearch"
                    />
                </div>
                <Button @click="performSearch">Buscar</Button>
            </div>

            <!-- Taxes Table -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Impuestos</CardTitle>
                    <Button as-child>
                        <a href="/taxes/create">
                            <Plus class="mr-2 h-4 w-4" />
                            Nuevo Impuesto
                        </a>
                    </Button>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Tipo</TableHead>
                                <TableHead>Tasa %</TableHead>
                                <TableHead>Cód. SUNAT</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>Por Defecto</TableHead>
                                <TableHead class="text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="tax in taxes" :key="tax.id">
                                <TableCell class="font-medium">
                                    {{ tax.name }}
                                    <br>
                                    <span v-if="tax.description" class="text-xs text-muted-foreground">
                                        {{ tax.description }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline">{{ tax.tax_type }}</Badge>
                                </TableCell>
                                <TableCell>
                                    <span class="font-mono">{{ tax.rate_percent }}%</span>
                                </TableCell>
                                <TableCell>
                                    <span v-if="tax.affectation_type_code" class="text-sm">
                                        {{ tax.affectation_type_code }}
                                    </span>
                                    <span v-else class="text-sm text-muted-foreground">-</span>
                                </TableCell>
                                <TableCell>
                                    <Badge v-if="tax.is_active" variant="default">Activo</Badge>
                                    <Badge v-else variant="secondary">Inactivo</Badge>
                                </TableCell>
                                <TableCell>
                                    <Badge v-if="tax.is_default" variant="default">Sí</Badge>
                                    <span v-else class="text-sm text-muted-foreground">-</span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            as-child
                                        >
                                            <a :href="`/taxes/${tax.id}/edit`">
                                                <Edit class="h-4 w-4" />
                                            </a>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            @click="toggleStatus(tax)"
                                            :title="tax.is_active ? 'Desactivar' : 'Activar'"
                                        >
                                            <Power class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            @click="openDeleteDialog(tax)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <div v-if="taxes.length === 0" class="text-center py-8 text-muted-foreground">
                        No se encontraron impuestos
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
                        Esta acción eliminará el impuesto "{{ taxToDelete?.name }}" permanentemente.
                        Esta acción no se puede deshacer.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancelar</AlertDialogCancel>
                    <AlertDialogAction @click="deleteTax">Eliminar</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
