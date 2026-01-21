<script setup lang="ts">
import FormPageHeader from '@/components/FormPageHeader.vue';
import InputError from '@/components/InputError.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { Snowflake } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface FreezeActor {
    id: number;
    name: string;
    email: string;
}

interface Freeze {
    id: number;
    status: 'active' | 'completed' | 'cancelled';
    freeze_start_date: string;
    freeze_end_date: string | null;
    days_frozen: number;
    planned_days: number;
    reason: string | null;
    requested_by: FreezeActor | null;
    approved_by: FreezeActor | null;
}

interface Props {
    subscription: {
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
        active_freeze: Freeze | null;
        freezes: Freeze[];
    };
    returnTo: string;
}

const props = defineProps<Props>();
const page = usePage<{ errors?: Record<string, string> }>();
const errors = computed(() => page.props.errors || {});

const freezeDialogOpen = ref(false);
const freezeStartDate = ref('');
const freezeEndDate = ref('');
const freezeReason = ref('');

const subscriptionTitle = computed(() => {
    const partner = props.subscription.partner?.display_name;
    const plan = props.subscription.plan?.name;
    return (
        [partner, plan].filter(Boolean).join(' · ') ||
        `Suscripción #${props.subscription.id}`
    );
});

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Suscripciones', href: '/subscriptions' },
    {
        title: subscriptionTitle.value,
        href: `/subscriptions/${props.subscription.id}`,
    },
]);

const statusBadge = computed(() => {
    const status = props.subscription.status;
    if (status === 'active')
        return { label: 'Activa', variant: 'default' as const };
    if (status === 'frozen')
        return { label: 'Congelada', variant: 'secondary' as const };
    if (status === 'expired')
        return { label: 'Vencida', variant: 'destructive' as const };
    return { label: 'Cancelada', variant: 'outline' as const };
});

const freezeStatusBadge = (status: Freeze['status']) => {
    if (status === 'active')
        return { label: 'Activo', variant: 'default' as const };
    if (status === 'completed')
        return { label: 'Completado', variant: 'secondary' as const };
    return { label: 'Cancelado', variant: 'outline' as const };
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

const goBack = () => {
    router.visit(props.returnTo || '/subscriptions', { preserveScroll: true });
};

const toLocalIsoDate = (date: Date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const addDays = (isoDate: string, days: number) => {
    const base = new Date(`${isoDate}T00:00:00`);
    base.setDate(base.getDate() + days);
    return toLocalIsoDate(base);
};

const openFreezeDialog = () => {
    const todayIso = toLocalIsoDate(new Date());
    freezeStartDate.value = todayIso;
    freezeEndDate.value = addDays(todayIso, 1);
    freezeReason.value = '';
    freezeDialogOpen.value = true;
};

const freezeSubscription = () => {
    router.post(
        `/subscriptions/${props.subscription.id}/freeze`,
        {
            freeze_start_date: freezeStartDate.value,
            freeze_end_date: freezeEndDate.value,
            reason: freezeReason.value || undefined,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                freezeDialogOpen.value = false;
            },
        },
    );
};

const unfreezeSubscription = () => {
    router.post(
        `/subscriptions/${props.subscription.id}/unfreeze`,
        {},
        { preserveScroll: true },
    );
};

const cancelSubscription = () => {
    router.delete(`/subscriptions/${props.subscription.id}`, {
        preserveScroll: true,
    });
};

const minFreezeStartDate = computed(() => {
    return toLocalIsoDate(new Date());
});

const minFreezeEndDate = computed(() => {
    if (!freezeStartDate.value) return '';
    return addDays(freezeStartDate.value, 1);
});

watch(
    () => freezeStartDate.value,
    () => {
        if (!freezeStartDate.value) return;
        const minEnd = minFreezeEndDate.value;
        if (!freezeEndDate.value || freezeEndDate.value < minEnd) {
            freezeEndDate.value = minEnd;
        }
    },
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <div class="min-w-0">
                    <FormPageHeader
                        :title="subscriptionTitle"
                        :description="`${statusBadge.label} · #${props.subscription.id} · Vigencia: ${formatDate(props.subscription.start_date)} → ${formatDate(props.subscription.end_date)} · Original: ${formatDate(props.subscription.original_end_date)}`"
                        :back-href="props.returnTo || '/subscriptions'"
                    >
                        <template #actions>
                            <Button
                                v-if="props.subscription.status === 'active'"
                                type="button"
                                variant="secondary"
                                @click="openFreezeDialog"
                            >
                                <Snowflake class="mr-2 h-4 w-4" />
                                Congelar
                            </Button>

                            <AlertDialog
                                v-if="props.subscription.status === 'frozen'"
                            >
                                <AlertDialogTrigger as-child>
                                    <Button type="button" variant="secondary">
                                        Descongelar
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle
                                            >¿Descongelar
                                            suscripción?</AlertDialogTitle
                                        >
                                        <AlertDialogDescription>
                                            Esto reactivará la suscripción y
                                            cerrará el congelamiento activo (si
                                            existe).
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>
                                            Cancelar
                                        </AlertDialogCancel>
                                        <AlertDialogAction
                                            @click="unfreezeSubscription"
                                        >
                                            Confirmar
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>

                            <AlertDialog
                                v-if="
                                    ['active', 'frozen'].includes(
                                        props.subscription.status,
                                    )
                                "
                            >
                                <AlertDialogTrigger as-child>
                                    <Button type="button" variant="destructive"
                                        >Cancelar</Button
                                    >
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle
                                            >¿Cancelar
                                            suscripción?</AlertDialogTitle
                                        >
                                        <AlertDialogDescription>
                                            Se marcará la suscripción como
                                            cancelada.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>
                                            Volver
                                        </AlertDialogCancel>
                                        <AlertDialogAction
                                            @click="cancelSubscription"
                                        >
                                            Confirmar
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </template>
                    </FormPageHeader>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm font-medium"
                            >Monto Pagado</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ formatMoney(props.subscription.amount_paid) }}
                        </div>
                        <div class="mt-1 text-xs text-muted-foreground">
                            {{ props.subscription.payment_method || '—' }}
                            <span v-if="props.subscription.payment_reference">
                                · {{ props.subscription.payment_reference }}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm font-medium"
                            >Congelamientos</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ props.subscription.remaining_freeze_days }}
                        </div>
                        <div class="mt-1 text-xs text-muted-foreground">
                            Días restantes · Usados:
                            {{ props.subscription.total_days_frozen }}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm font-medium">Uso</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ props.subscription.entries_this_month }}
                        </div>
                        <div class="mt-1 text-xs text-muted-foreground">
                            Entradas este mes · Total:
                            {{ props.subscription.entries_used }}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="props.subscription.active_freeze">
                <CardHeader>
                    <CardTitle>Congelamiento activo</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div>
                            <div class="text-xs text-muted-foreground">
                                Inicio
                            </div>
                            <div class="text-sm font-medium">
                                {{
                                    formatDate(
                                        props.subscription.active_freeze
                                            .freeze_start_date,
                                    )
                                }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-muted-foreground">
                                Fin (planeado)
                            </div>
                            <div class="text-sm font-medium">
                                {{
                                    formatDate(
                                        props.subscription.active_freeze
                                            .freeze_end_date,
                                    )
                                }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-muted-foreground">
                                Días
                            </div>
                            <div class="text-sm font-medium">
                                {{
                                    props.subscription.active_freeze
                                        .planned_days
                                }}
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="props.subscription.active_freeze.reason"
                        class="mt-3 text-sm"
                    >
                        <span class="text-muted-foreground">Motivo:</span>
                        {{ props.subscription.active_freeze.reason }}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Historial de congelamientos</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Estado</TableHead>
                                <TableHead>Inicio</TableHead>
                                <TableHead>Fin</TableHead>
                                <TableHead>Planeado</TableHead>
                                <TableHead>Real</TableHead>
                                <TableHead>Motivo</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-if="props.subscription.freezes.length === 0"
                            >
                                <TableCell colspan="6" class="text-center">
                                    Sin congelamientos
                                </TableCell>
                            </TableRow>

                            <TableRow
                                v-for="freeze in props.subscription.freezes"
                                :key="freeze.id"
                            >
                                <TableCell>
                                    <Badge
                                        :variant="
                                            freezeStatusBadge(freeze.status)
                                                .variant
                                        "
                                    >
                                        {{
                                            freezeStatusBadge(freeze.status)
                                                .label
                                        }}
                                    </Badge>
                                </TableCell>
                                <TableCell>{{
                                    formatDate(freeze.freeze_start_date)
                                }}</TableCell>
                                <TableCell>{{
                                    formatDate(freeze.freeze_end_date)
                                }}</TableCell>
                                <TableCell>{{ freeze.planned_days }}</TableCell>
                                <TableCell>{{ freeze.days_frozen }}</TableCell>
                                <TableCell class="max-w-[420px]">
                                    <div
                                        class="truncate text-sm text-muted-foreground"
                                    >
                                        {{ freeze.reason || '—' }}
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <Dialog v-model:open="freezeDialogOpen">
                <DialogContent class="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Congelar suscripción</DialogTitle>
                        <DialogDescription>
                            Esto extenderá la fecha de fin inmediatamente y
                            marcará el estado como congelado.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-3">
                        <div>
                            <div class="text-sm font-medium">Inicio</div>
                            <Input
                                v-model="freezeStartDate"
                                type="date"
                                :min="minFreezeStartDate"
                            />
                            <InputError :message="errors.freeze_start_date" />
                        </div>
                        <div>
                            <div class="text-sm font-medium">Fin</div>
                            <Input
                                v-model="freezeEndDate"
                                type="date"
                                :min="minFreezeEndDate"
                            />
                            <InputError :message="errors.freeze_end_date" />
                        </div>
                        <div>
                            <div class="text-sm font-medium">
                                Motivo (opcional)
                            </div>
                            <Textarea
                                v-model="freezeReason"
                                placeholder="Ej: Viaje / salud / etc."
                            />
                            <InputError :message="errors.reason" />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="freezeDialogOpen = false"
                        >
                            Cancelar
                        </Button>
                        <Button type="button" @click="freezeSubscription"
                            >Confirmar</Button
                        >
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
