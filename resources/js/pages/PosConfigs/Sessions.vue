<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';

interface User {
    id: number;
    name: string;
    email: string;
}

interface Warehouse {
    id: number;
    name: string;
}

interface Tax {
    id: number;
    name: string;
}

interface PosConfig {
    id: number;
    name: string;
    warehouse: Warehouse;
    tax?: Tax;
}

interface PosSession {
    id: number;
    user: User;
    opening_balance: string;
    closing_balance: string | null;
    opened_at: string;
    closed_at: string | null;
    status: string;
}

interface Props {
    posConfig: PosConfig;
    sessions: {
        data: PosSession[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

const props = defineProps<Props>();

const getStatusVariant = (status: string): 'default' | 'secondary' | 'outline' | 'destructive' => {
    switch (status) {
        case 'opened':
            return 'default';
        case 'closed':
            return 'secondary';
        case 'opening_control':
        case 'closing_control':
            return 'outline';
        default:
            return 'outline';
    }
};

const getStatusLabel = (status: string): string => {
    switch (status) {
        case 'opening_control':
            return 'Control de Apertura';
        case 'opened':
            return 'Abierta';
        case 'closing_control':
            return 'Control de Cierre';
        case 'closed':
            return 'Cerrada';
        default:
            return status;
    }
};

const formatCurrency = (value: string | null): string => {
    if (!value) return '-';
    return `S/ ${parseFloat(value).toFixed(2)}`;
};

const formatDateTime = (dateTime: string | null): string => {
    if (!dateTime) return '-';
    return new Date(dateTime).toLocaleString('es-PE', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const calculateDuration = (openedAt: string, closedAt: string | null): string => {
    if (!closedAt) return 'En progreso';
    
    const start = new Date(openedAt);
    const end = new Date(closedAt);
    const diffMs = end.getTime() - start.getTime();
    
    const hours = Math.floor(diffMs / (1000 * 60 * 60));
    const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
    
    return `${hours}h ${minutes}m`;
};
</script>

<template>
    <Head :title="`Sesiones - ${posConfig.name}`" />

    <AppLayout>
        <div class="flex flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="icon" @click="$inertia.visit('/pos-configs')">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
                <div class="flex-1">
                    <h1 class="text-3xl font-bold tracking-tight">Historial de Sesiones</h1>
                    <p class="text-muted-foreground">
                        {{ posConfig.name }} - {{ posConfig.warehouse.name }}
                    </p>
                </div>
            </div>

            <!-- Sessions Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Sesiones Registradas ({{ sessions.total }})</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Usuario</TableHead>
                                <TableHead>Apertura</TableHead>
                                <TableHead>Balance Inicial</TableHead>
                                <TableHead>Balance Final</TableHead>
                                <TableHead>Cierre</TableHead>
                                <TableHead>Duración</TableHead>
                                <TableHead>Estado</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="session in sessions.data" :key="session.id">
                                <TableCell class="font-medium">
                                    {{ session.user.name }}
                                    <div class="text-xs text-muted-foreground">
                                        {{ session.user.email }}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    {{ formatDateTime(session.opened_at) }}
                                </TableCell>
                                <TableCell>
                                    {{ formatCurrency(session.opening_balance) }}
                                </TableCell>
                                <TableCell>
                                    {{ formatCurrency(session.closing_balance) }}
                                </TableCell>
                                <TableCell>
                                    {{ formatDateTime(session.closed_at) }}
                                </TableCell>
                                <TableCell>
                                    {{ calculateDuration(session.opened_at, session.closed_at) }}
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="getStatusVariant(session.status)">
                                        {{ getStatusLabel(session.status) }}
                                    </Badge>
                                </TableCell>
                            </TableRow>

                            <TableRow v-if="sessions.data.length === 0">
                                <TableCell colspan="7" class="text-center text-muted-foreground py-8">
                                    No hay sesiones registradas para este POS
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
