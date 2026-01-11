<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Head, router } from '@inertiajs/vue3';
import { Plus, Search, Pencil, Trash2, Power, History, DoorOpen } from 'lucide-vue-next';
import { ref } from 'vue';

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

interface Props {
    posConfigs: {
        data: PosConfig[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

defineProps<Props>();
const searchQuery = ref('');

const handleSearch = () => {
    router.get('/pos-configs', { search: searchQuery.value }, { preserveState: true });
};

const toggleStatus = (posConfig: PosConfig) => {
    router.post(`/pos-configs/${posConfig.id}/toggle-status`, {}, {
        preserveScroll: true,
    });
};

const deletePosConfig = (posConfig: PosConfig) => {
    if (confirm(`¿Estás seguro de eliminar el POS "${posConfig.name}"?`)) {
        router.delete(`/pos-configs/${posConfig.id}`);
    }
};
</script>

<template>
    <Head title="Configuración POS" />

    <AppLayout>
        <div class="flex flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Configuración POS</h1>
                    <p class="text-muted-foreground">Gestiona los puntos de venta</p>
                </div>
                <Button @click="$inertia.visit('/pos-configs/create')">
                    <Plus class="h-4 w-4 mr-2" />
                    Nuevo POS
                </Button>
            </div>

            <!-- Search -->
            <Card>
                <CardContent class="pt-6">
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
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
                    <CardTitle>POS Registrados ({{ posConfigs.total }})</CardTitle>
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
                                <TableHead class="text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="posConfig in posConfigs.data" :key="posConfig.id">
                                <TableCell class="font-medium">
                                    {{ posConfig.name }}
                                </TableCell>
                                <TableCell>
                                    {{ posConfig.warehouse.name }}
                                </TableCell>
                                <TableCell>
                                    <div v-if="posConfig.tax">
                                        {{ posConfig.tax.name }}
                                        <span class="text-muted-foreground text-sm">
                                            ({{ posConfig.tax.rate_percent }}%)
                                        </span>
                                    </div>
                                    <span v-else class="text-muted-foreground">-</span>
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline">
                                        {{ posConfig.journals.length }} diarios
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="posConfig.is_active ? 'default' : 'secondary'">
                                        {{ posConfig.is_active ? 'Activo' : 'Inactivo' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            v-if="posConfig.is_active"
                                            size="icon"
                                            @click="$inertia.visit(`/pos/open?config=${posConfig.id}`)"
                                            title="Abrir Caja"
                                        >
                                            <DoorOpen class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            @click="$inertia.visit(`/pos-configs/${posConfig.id}/sessions`)"
                                            title="Ver historial de sesiones"
                                        >
                                            <History class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            @click="$inertia.visit(`/pos-configs/${posConfig.id}/edit`)"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            :class="posConfig.is_active ? 'text-green-600' : 'text-gray-400'"
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

                            <TableRow v-if="posConfigs.data.length === 0">
                                <TableCell colspan="6" class="text-center text-muted-foreground py-8">
                                    No hay configuraciones POS registradas
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
