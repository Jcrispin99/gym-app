<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectItemText,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { Filter, Search, Snowflake } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface PlanOption {
    id: number;
    name: string;
}

interface Freeze {
    id: number;
    status: 'active' | 'completed' | 'cancelled';
    freeze_start_date: string;
    freeze_end_date: string | null;
    days_frozen: number;
    planned_days: number;
    reason: string | null;
}

interface Subscription {
    id: number;
    status: 'active' | 'frozen' | 'expired' | 'cancelled';
    start_date: string;
    end_date: string;
    original_end_date: string;
    amount_paid: number;
    payment_method: string | null;
    payment_reference: string | null;
    entries_used: number;
    entries_this_month: number;
    total_days_frozen: number;
    remaining_freeze_days: number;
    partner: {
        id: number;
        display_name: string;
        document_type: string;
        document_number: string;
        email: string | null;
        phone: string | null;
    } | null;
    plan: {
        id: number;
        name: string;
        duration_days: number;
        price: number;
        allows_freezing: boolean;
        max_freeze_days: number;
    } | null;
    freezes: Freeze[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    subscriptions: {
        data: Subscription[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    plans: PlanOption[];
    filters: {
        search: string;
        status: string;
        plan_id: number | null;
    };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || 'all');
const planFilter = ref(
    props.filters.plan_id ? String(props.filters.plan_id) : 'all',
);

const applyFilters = () => {
    router.get(
        '/subscriptions',
        {
            search: searchQuery.value || undefined,
            status:
                statusFilter.value !== 'all' ? statusFilter.value : undefined,
            plan_id:
                planFilter.value !== 'all'
                    ? Number(planFilter.value)
                    : undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
};

const clearFilters = () => {
    searchQuery.value = '';
    statusFilter.value = 'all';
    planFilter.value = 'all';
    applyFilters();
};

const statusBadge = (status: Subscription['status']) => {
    if (status === 'active')
        return { label: 'Activa', variant: 'default' as const };
    if (status === 'frozen')
        return { label: 'Congelada', variant: 'secondary' as const };
    if (status === 'expired')
        return { label: 'Vencida', variant: 'destructive' as const };
    return { label: 'Cancelada', variant: 'outline' as const };
};

const formatDate = (date: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('es-PE', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
    });
};

const formatMoney = (value: number) => {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
    }).format(value);
};

const activeFreezeFor = (subscription: Subscription) => {
    return subscription.freezes.find((f) => f.status === 'active') || null;
};

const filtersActive = computed(() => {
    return Boolean(
        searchQuery.value ||
        statusFilter.value !== 'all' ||
        planFilter.value !== 'all',
    );
});

const goToSubscription = (subscription: Subscription) => {
    const returnTo = `${window.location.pathname}${window.location.search}`;
    router.visit(
        `/subscriptions/${subscription.id}?return_to=${encodeURIComponent(returnTo)}`,
    );
};
</script>

<template>
    <AppLayout title="Suscripciones">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">
                        Suscripciones
                    </h1>
                    <p class="text-muted-foreground">
                        Panel administrativo de suscripciones y congelamientos
                    </p>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Filter class="h-4 w-4" />
                        Filtros
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                        <div class="relative md:col-span-2">
                            <Search
                                class="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground"
                            />
                            <Input
                                v-model="searchQuery"
                                placeholder="Buscar por nombre, documento o plan..."
                                class="pl-8"
                                @keyup.enter="applyFilters"
                            />
                        </div>

                        <Select v-model="statusFilter">
                            <SelectTrigger>
                                <SelectValue placeholder="Estado" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">
                                        <SelectItemText>Todos</SelectItemText>
                                    </SelectItem>
                                    <SelectItem value="active">
                                        <SelectItemText>Activas</SelectItemText>
                                    </SelectItem>
                                    <SelectItem value="frozen">
                                        <SelectItemText
                                            >Congeladas</SelectItemText
                                        >
                                    </SelectItem>
                                    <SelectItem value="expired">
                                        <SelectItemText
                                            >Vencidas</SelectItemText
                                        >
                                    </SelectItem>
                                    <SelectItem value="cancelled">
                                        <SelectItemText
                                            >Canceladas</SelectItemText
                                        >
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>

                        <Select v-model="planFilter">
                            <SelectTrigger>
                                <SelectValue placeholder="Plan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">
                                        <SelectItemText
                                            >Todos los planes</SelectItemText
                                        >
                                    </SelectItem>
                                    <SelectItem
                                        v-for="plan in props.plans"
                                        :key="plan.id"
                                        :value="String(plan.id)"
                                    >
                                        <SelectItemText>{{
                                            plan.name
                                        }}</SelectItemText>
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="mt-3 flex items-center gap-2">
                        <Button type="button" @click="applyFilters"
                            >Aplicar</Button
                        >
                        <Button
                            v-if="filtersActive"
                            type="button"
                            variant="outline"
                            @click="clearFilters"
                        >
                            Limpiar
                        </Button>
                        <div class="ml-auto text-sm text-muted-foreground">
                            Total: {{ props.subscriptions.total }}
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Listado</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Miembro</TableHead>
                                <TableHead>Plan</TableHead>
                                <TableHead>Vigencia</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>Freeze</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-if="props.subscriptions.data.length === 0"
                            >
                                <TableCell colspan="5" class="text-center">
                                    No hay suscripciones con esos filtros
                                </TableCell>
                            </TableRow>

                            <TableRow
                                v-for="subscription in props.subscriptions.data"
                                :key="subscription.id"
                                class="cursor-pointer hover:bg-accent/50"
                                @click="goToSubscription(subscription)"
                            >
                                <TableCell>
                                    <div class="font-medium">
                                        {{
                                            subscription.partner
                                                ?.display_name || '—'
                                        }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            subscription.partner
                                                ? `${subscription.partner.document_type} ${subscription.partner.document_number}`
                                                : ''
                                        }}
                                    </div>
                                </TableCell>

                                <TableCell>
                                    <div class="font-medium">
                                        {{ subscription.plan?.name || '—' }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            formatMoney(
                                                subscription.amount_paid,
                                            )
                                        }}
                                    </div>
                                </TableCell>

                                <TableCell>
                                    <div class="text-sm">
                                        {{
                                            formatDate(subscription.start_date)
                                        }}
                                        →
                                        {{ formatDate(subscription.end_date) }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        Original:
                                        {{
                                            formatDate(
                                                subscription.original_end_date,
                                            )
                                        }}
                                    </div>
                                </TableCell>

                                <TableCell>
                                    <Badge
                                        :variant="
                                            statusBadge(subscription.status)
                                                .variant
                                        "
                                    >
                                        {{
                                            statusBadge(subscription.status)
                                                .label
                                        }}
                                    </Badge>
                                </TableCell>

                                <TableCell>
                                    <div class="text-sm">
                                        Restantes:
                                        <span class="font-medium">
                                            {{
                                                subscription.remaining_freeze_days
                                            }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        Usados:
                                        {{ subscription.total_days_frozen }}
                                    </div>
                                    <div
                                        v-if="activeFreezeFor(subscription)"
                                        class="mt-1 flex items-center gap-1 text-xs text-blue-700"
                                    >
                                        <Snowflake class="h-3.5 w-3.5" />
                                        Activo hasta
                                        {{
                                            formatDate(
                                                activeFreezeFor(subscription)
                                                    ?.freeze_end_date || null,
                                            )
                                        }}
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
