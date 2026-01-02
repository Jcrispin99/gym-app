<script setup lang="ts">
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
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
import { Head, router } from '@inertiajs/vue3';
import { Plus, Edit, Trash2, CreditCard, Search, Clock, Calendar, Snowflake } from 'lucide-vue-next';

interface Company {
    id: number;
    business_name: string;
    trade_name: string;
}

interface MembershipPlan {
    id: number;
    company_id: number;
    company: Company;
    name: string;
    description: string | null;
    duration_days: number;
    price: number;
    max_entries_per_month: number | null;
    max_entries_per_day: number;
    time_restricted: boolean;
    allowed_time_start: string | null;
    allowed_time_end: string | null;
    allowed_days: string[] | null;
    allows_freezing: boolean;
    max_freeze_days: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

interface Props {
    plans: MembershipPlan[];
}

const props = defineProps<Props>();

const deleteDialogOpen = ref(false);
const planToDelete = ref<MembershipPlan | null>(null);
const searchQuery = ref('');

const openDeleteDialog = (plan: MembershipPlan) => {
    planToDelete.value = plan;
    deleteDialogOpen.value = true;
};

const deletePlan = () => {
    if (planToDelete.value) {
        router.delete(`/membership-plans/${planToDelete.value.id}`, {
            onSuccess: () => {
                deleteDialogOpen.value = false;
                planToDelete.value = null;
            },
        });
    }
};

const toggleStatus = (plan: MembershipPlan) => {
    router.post(`/membership-plans/${plan.id}/toggle-status`, {}, {
        preserveScroll: true,
    });
};

const formatPrice = (price: number | string) => {
    const numPrice = typeof price === 'string' ? parseFloat(price) : price;
    return `S/ ${numPrice.toFixed(2)}`;
};

const getDurationLabel = (days: number) => {
    if (days === 30) return '1 mes';
    if (days === 60) return '2 meses';
    if (days === 90) return '3 meses';
    if (days === 180) return '6 meses';
    if (days === 365) return '1 año';
    return `${days} días`;
};

const getEntriesLabel = (entries: number | null) => {
    if (entries === null) return 'Ilimitado';
    return `${entries} entradas/mes`;
};

// Filtered plans
const filteredPlans = computed(() => {
    if (!searchQuery.value) return props.plans;
    
    const query = searchQuery.value.toLowerCase();
    return props.plans.filter(plan =>
        plan.name.toLowerCase().includes(query) ||
        plan.description?.toLowerCase().includes(query)
    );
});

// Stats
const totalPlans = computed(() => props.plans.length);
const activePlans = computed(() => props.plans.filter(p => p.is_active).length);
const averagePrice = computed(() => {
    if (props.plans.length === 0) return 0;
    const sum = props.plans.reduce((acc, p) => {
        const price = typeof p.price === 'string' ? parseFloat(p.price) : p.price;
        return acc + price;
    }, 0);
    return sum / props.plans.length;
});
</script>

<template>
    <AppLayout>
        <Head title="Planes de Membresía" />

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Planes de Membresía</h1>
                    <p class="text-muted-foreground">
                        Gestiona los planes de membresía disponibles para tus clientes
                    </p>
                </div>
                <Button @click="router.visit('/membership-plans/create')">
                    <Plus class="mr-2 h-4 w-4" />
                    Nuevo Plan
                </Button>
            </div>

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Planes</CardTitle>
                        <CreditCard class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ totalPlans }}</div>
                        <p class="text-xs text-muted-foreground">
                            {{ activePlans }} activos
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Precio Promedio</CardTitle>
                        <CreditCard class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ formatPrice(averagePrice) }}</div>
                        <p class="text-xs text-muted-foreground">
                            Precio promedio mensual
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Planes Activos</CardTitle>
                        <CreditCard class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ activePlans }}</div>
                        <p class="text-xs text-muted-foreground">
                            Disponibles para venta
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Plans Table -->
            <Card>
                <CardHeader>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <CardTitle>Listado de Planes</CardTitle>
                            <CardDescription>
                                Mostrando {{ filteredPlans.length }} de {{ plans.length }} planes
                            </CardDescription>
                        </div>

                        <!-- Search -->
                        <div class="relative w-[300px]">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Buscar..."
                                class="pl-10"
                            />
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Duración</TableHead>
                                <TableHead>Precio</TableHead>
                                <TableHead>Entradas</TableHead>
                                <TableHead>Restricciones</TableHead>
                                <TableHead>Congelamiento</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead class="text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="plan in filteredPlans" :key="plan.id">
                                <!-- Nombre -->
                                <TableCell>
                                    <div>
                                        <div class="font-medium">{{ plan.name }}</div>
                                        <div v-if="plan.description" class="text-sm text-muted-foreground line-clamp-1">
                                            {{ plan.description }}
                                        </div>
                                    </div>
                                </TableCell>

                                <!-- Duración -->
                                <TableCell>
                                    <div class="flex items-center gap-2">
                                        <Calendar class="h-4 w-4 text-muted-foreground" />
                                        <span>{{ getDurationLabel(plan.duration_days) }}</span>
                                    </div>
                                </TableCell>

                                <!-- Precio -->
                                <TableCell>
                                    <div class="font-semibold">{{ formatPrice(plan.price) }}</div>
                                </TableCell>

                                <!-- Entradas -->
                                <TableCell>
                                    <Badge :variant="plan.max_entries_per_month === null ? 'default' : 'secondary'">
                                        {{ getEntriesLabel(plan.max_entries_per_month) }}
                                    </Badge>
                                </TableCell>

                                <!-- Restricciones -->
                                <TableCell>
                                    <div class="flex flex-col gap-1">
                                        <Badge v-if="plan.time_restricted" variant="outline" class="w-fit">
                                            <Clock class="mr-1 h-3 w-3" />
                                            {{ plan.allowed_time_start }} - {{ plan.allowed_time_end }}
                                        </Badge>
                                        <Badge v-if="plan.allowed_days && plan.allowed_days.length < 7" variant="outline" class="w-fit">
                                            {{ plan.allowed_days.length }} días/semana
                                        </Badge>
                                        <span v-if="!plan.time_restricted && (!plan.allowed_days || plan.allowed_days.length === 7)" class="text-sm text-muted-foreground">
                                            Sin restricciones
                                        </span>
                                    </div>
                                </TableCell>

                                <!-- Congelamiento -->
                                <TableCell>
                                    <div v-if="plan.allows_freezing" class="flex items-center gap-2">
                                        <Snowflake class="h-4 w-4 text-blue-500" />
                                        <span class="text-sm">{{ plan.max_freeze_days }} días</span>
                                    </div>
                                    <span v-else class="text-sm text-muted-foreground">No permite</span>
                                </TableCell>

                                <!-- Estado -->
                                <TableCell>
                                    <Badge
                                        :variant="plan.is_active ? 'default' : 'secondary'"
                                        class="cursor-pointer"
                                        @click="toggleStatus(plan)"
                                    >
                                        {{ plan.is_active ? 'Activo' : 'Inactivo' }}
                                    </Badge>
                                </TableCell>

                                <!-- Acciones -->
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            @click="router.visit(`/membership-plans/${plan.id}/edit`)"
                                        >
                                            <Edit class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            @click="openDeleteDialog(plan)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>

                            <TableRow v-if="filteredPlans.length === 0">
                                <TableCell colspan="8" class="text-center text-muted-foreground">
                                    No se encontraron planes
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>

        <!-- Delete Confirmation Dialog -->
        <AlertDialog :open="deleteDialogOpen" @update:open="deleteDialogOpen = $event">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>¿Estás seguro?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Esta acción no se puede deshacer. Se eliminará permanentemente el plan
                        <strong>{{ planToDelete?.name }}</strong>.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancelar</AlertDialogCancel>
                    <AlertDialogAction @click="deletePlan">
                        Eliminar
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
