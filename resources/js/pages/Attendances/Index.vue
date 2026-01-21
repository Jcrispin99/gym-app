<script setup lang="ts">
import FormPageHeader from '@/components/FormPageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { Calendar, Clock, LogOut, UserCheck } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    attendances: {
        data: Attendance[];
        links: any;
        meta: any;
    };
    stats: {
        today_total: number;
        active_now: number;
        today_denied: number;
    };
    filters: {
        date?: string;
        status?: string;
        search?: string;
    };
}

interface Attendance {
    id: number;
    partner: {
        full_name?: string;
        first_name?: string;
        last_name?: string;
        business_name?: string;
        dni: string;
        photo_url: string | null;
    };
    subscription: {
        plan: {
            name: string;
        };
    } | null;
    check_in_time: string;
    check_out_time: string | null;
    duration_minutes: number | null;
    status: 'valid' | 'denied' | 'manual_override';
    validation_message: string | null;
    is_manual_entry: boolean;
    registered_by: {
        id: number;
        name: string;
        email: string;
    } | null;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Asistencias', href: '/attendances' },
];

const search = ref(props.filters.search || '');
const selectedDate = ref(
    props.filters.date || new Date().toISOString().slice(0, 10),
);
const selectedStatus = ref(props.filters.status || '');

const applyFilters = () => {
    router.get(
        '/attendances',
        {
            search: search.value,
            date: selectedDate.value,
            status: selectedStatus.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const checkOut = (attendance: Attendance) => {
    if (confirm('¿Registrar salida para este miembro?')) {
        router.post(`/attendances/${attendance.id}/check-out`);
    }
};

const formatTime = (datetime: string) => {
    return new Date(datetime).toLocaleTimeString('es-PE', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatDate = (datetime: string) => {
    return new Date(datetime).toLocaleDateString('es-PE', {
        day: '2-digit',
        month: 'short',
    });
};

const formatDuration = (minutes: number | null) => {
    if (!minutes) return '-';
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return hours > 0 ? `${hours}h ${mins}m` : `${mins}m`;
};

const getStatusBadge = (status: string) => {
    const badges = {
        valid: { variant: 'default' as const, label: 'Válida' },
        denied: { variant: 'destructive' as const, label: 'Denegada' },
        manual_override: { variant: 'secondary' as const, label: 'Manual' },
    };
    return badges[status as keyof typeof badges] || badges.valid;
};

const getPartnerDisplayName = (partner: Attendance['partner']) => {
    const name =
        partner.business_name ||
        [partner.first_name, partner.last_name].filter(Boolean).join(' ') ||
        partner.full_name ||
        '';
    return name.trim();
};
</script>

<template>
    <Head title="Asistencias" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <FormPageHeader
                title="Asistencias"
                description="Historial de entradas y salidas"
                :show-back="false"
            >
                <template #actions>
                    <Button @click="router.visit('/attendances/check-in')">
                        <UserCheck class="mr-2 h-4 w-4" />
                        Registrar Asistencia
                    </Button>
                </template>
            </FormPageHeader>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Entradas Hoy</CardTitle
                        >
                        <UserCheck class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ stats.today_total }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Accesos válidos
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Activos Ahora</CardTitle
                        >
                        <Clock class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ stats.active_now }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Sin check-out
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Denegadas Hoy</CardTitle
                        >
                        <Calendar class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-red-600">
                            {{ stats.today_denied }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Intentos rechazados
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters -->
            <Card>
                <CardHeader>
                    <CardTitle>Filtros</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex gap-4">
                        <Input
                            v-model="search"
                            type="text"
                            placeholder="Buscar por nombre o DNI..."
                            class="flex-1"
                            @keyup.enter="applyFilters"
                        />
                        <Input
                            v-model="selectedDate"
                            type="date"
                            @change="applyFilters"
                        />
                        <Button @click="applyFilters">Filtrar</Button>
                        <Button
                            variant="outline"
                            @click="
                                search = '';
                                selectedDate = '';
                                selectedStatus = '';
                                applyFilters();
                            "
                        >
                            Limpiar
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Registros de Asistencia</CardTitle>
                    <CardDescription
                        >{{ attendances.meta?.total || 0 }} registros
                        totales</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Miembro</TableHead>
                                <TableHead>Plan</TableHead>
                                <TableHead>Entrada</TableHead>
                                <TableHead>Salida</TableHead>
                                <TableHead>Duración</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>Registrado por</TableHead>
                                <TableHead class="text-right"
                                    >Acciones</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="attendance in attendances.data"
                                :key="attendance.id"
                            >
                                <TableCell>
                                    <div class="flex items-center gap-3">
                                        <div
                                            v-if="attendance.partner.photo_url"
                                            class="flex-shrink-0"
                                        >
                                            <img
                                                :src="
                                                    attendance.partner.photo_url
                                                "
                                                :alt="
                                                    getPartnerDisplayName(
                                                        attendance.partner,
                                                    )
                                                "
                                                class="h-10 w-10 rounded-full object-cover"
                                            />
                                        </div>
                                        <div
                                            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-muted font-bold"
                                            v-else
                                        >
                                            {{
                                                getPartnerDisplayName(
                                                    attendance.partner,
                                                ).charAt(0) || '?'
                                            }}
                                        </div>
                                        <div>
                                            <p class="font-medium">
                                                {{
                                                    getPartnerDisplayName(
                                                        attendance.partner,
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="text-sm text-muted-foreground"
                                            >
                                                {{ attendance.partner.dni }}
                                                attendance.partner
                                                .document_number }}
                                            </p>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span v-if="attendance.subscription">
                                        {{ attendance.subscription.plan.name }}
                                    </span>
                                    <span v-else class="text-muted-foreground"
                                        >-</span
                                    >
                                </TableCell>
                                <TableCell>
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{
                                            formatTime(attendance.check_in_time)
                                        }}</span>
                                        <span
                                            class="text-xs text-muted-foreground"
                                            >{{
                                                formatDate(
                                                    attendance.check_in_time,
                                                )
                                            }}</span
                                        >
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span
                                        v-if="attendance.check_out_time"
                                        class="font-medium"
                                    >
                                        {{
                                            formatTime(
                                                attendance.check_out_time,
                                            )
                                        }}
                                    </span>
                                    <Badge v-else variant="outline"
                                        >Activo</Badge
                                    >
                                </TableCell>
                                <TableCell>
                                    {{
                                        formatDuration(
                                            attendance.duration_minutes,
                                        )
                                    }}
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="
                                            getStatusBadge(attendance.status)
                                                .variant
                                        "
                                    >
                                        {{
                                            getStatusBadge(attendance.status)
                                                .label
                                        }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-2">
                                        <span>{{
                                            attendance.registered_by?.name ||
                                            '—'
                                        }}</span>
                                        <Badge
                                            v-if="
                                                attendance.is_manual_entry ||
                                                attendance.status ===
                                                    'manual_override'
                                            "
                                            variant="outline"
                                            >Manual</Badge
                                        >
                                    </div>
                                </TableCell>
                                <TableCell class="text-right">
                                    <Button
                                        v-if="!attendance.check_out_time"
                                        variant="outline"
                                        size="sm"
                                        @click="checkOut(attendance)"
                                    >
                                        <LogOut class="mr-2 h-4 w-4" />
                                        Check-Out
                                    </Button>
                                </TableCell>
                            </TableRow>

                            <TableRow v-if="attendances.data.length === 0">
                                <TableCell
                                    colspan="8"
                                    class="py-8 text-center text-muted-foreground"
                                >
                                    No se encontraron registros
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
