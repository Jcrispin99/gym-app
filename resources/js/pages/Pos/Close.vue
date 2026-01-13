<script setup lang="ts">
import CashCounterModal from '@/components/pos/CashCounterModal.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import PosLayout from '@/layouts/PosLayout.vue';
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    Calculator,
    DollarSign,
    TrendingDown,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

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
    rate_percent: number;
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
    pos_config: PosConfig;
    opening_balance: string;
    opened_at: string;
}

interface PaymentMethod {
    id: number;
    name: string;
    is_active: boolean;
}

interface SystemPaymentByMethod {
    payment_method_id: number;
    total: number;
}

interface SystemSummary {
    sales_count: number;
    sales_total: number;
    payments_total: number;
    payments_by_method: SystemPaymentByMethod[];
}

interface Props {
    session: PosSession;
    paymentMethods: PaymentMethod[];
    systemSummary: SystemSummary;
}

const props = defineProps<Props>();

const closingBalance = ref<string>('0.00');
const closingNote = ref<string>('');
const isSubmitting = ref(false);
const showCounterModal = ref(false);

const isEmbed = computed(() => {
    if (typeof window === 'undefined') return false;
    return new URLSearchParams(window.location.search).get('embed') === '1';
});

const notifyParentClose = (redirectTo?: string) => {
    if (typeof window === 'undefined') return;
    if (window.parent === window) return;
    window.parent.postMessage(
        { type: 'pos:close-modal', sessionId: props.session.id, redirectTo },
        window.location.origin,
    );
};

// Payment methods distribution
const payments = ref<Record<number, string>>(
    props.paymentMethods.reduce(
        (acc, method) => {
            acc[method.id] = '0.00';
            return acc;
        },
        {} as Record<number, string>,
    ),
);

const difference = computed(() => {
    const opening = parseFloat(props.session.opening_balance);
    const closing = parseFloat(closingBalance.value);
    return closing - opening;
});

const paymentsTotal = computed(() => {
    return Object.values(payments.value).reduce((sum, amount) => {
        return sum + parseFloat(amount || '0');
    }, 0);
});

const paymentsDifference = computed(() => {
    const closing = parseFloat(closingBalance.value);
    return paymentsTotal.value - closing;
});

const isPaymentsValid = computed(() => {
    return Math.abs(paymentsDifference.value) < 0.01;
});

const formatCurrency = (value: number | string): string => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return `S/ ${num.toFixed(2)}`;
};

const formatDateTime = (dateTime: string): string => {
    return new Date(dateTime).toLocaleString('es-PE', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const handleCounterConfirm = (amount: number) => {
    closingBalance.value = amount.toFixed(2);
};

const systemPaymentsByMethod = computed(() => {
    const map = new Map<number, number>();
    for (const row of props.systemSummary?.payments_by_method || []) {
        map.set(row.payment_method_id, Number(row.total || 0));
    }
    return map;
});

const systemPaymentsTotal = computed(() => {
    return Number(props.systemSummary?.payments_total || 0);
});

const systemSalesTotal = computed(() => {
    return Number(props.systemSummary?.sales_total || 0);
});

const systemGap = computed(() => {
    return systemPaymentsTotal.value - systemSalesTotal.value;
});

const applySystemSuggestion = () => {
    closingBalance.value = systemPaymentsTotal.value.toFixed(2);
    for (const method of props.paymentMethods) {
        const amount = systemPaymentsByMethod.value.get(method.id) || 0;
        payments.value[method.id] = amount.toFixed(2);
    }
};

const applySystemAmountForMethod = (methodId: number) => {
    const amount = systemPaymentsByMethod.value.get(methodId) || 0;
    payments.value[methodId] = amount.toFixed(2);
};

const handleSubmit = () => {
    if (isSubmitting.value) return;

    isSubmitting.value = true;

    const paymentsArray = props.paymentMethods
        .map((method) => ({
            payment_method_id: method.id,
            amount: parseFloat(payments.value[method.id] || '0'),
        }))
        .filter((p) => p.amount > 0);

    router.post(
        `/pos/${props.session.id}/close`,
        {
            closing_balance: parseFloat(closingBalance.value),
            closing_note: closingNote.value || null,
            payments: paymentsArray,
        },
        {
            onSuccess: () => {
                if (isEmbed.value) {
                    notifyParentClose('/pos-configs');
                }
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};

const handleCancel = () => {
    if (isEmbed.value) {
        notifyParentClose();
        return;
    }
    router.visit(`/pos/${props.session.id}`);
};
</script>

<template>
    <PosLayout
        v-if="!isEmbed"
        :title="`Cierre de Caja - ${session.pos_config.name}`"
    >
        <div class="mx-auto mt-8 max-w-[76.8rem] px-4 pb-10">
            <Button variant="ghost" class="mb-4" @click="handleCancel">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Volver al Dashboard
            </Button>

            <Card>
                <CardHeader>
                    <CardTitle>Cierre de Caja</CardTitle>
                    <CardDescription>
                        Registra el balance final y distribuye por método de
                        pago
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div class="space-y-3 rounded-lg border bg-muted/30 p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div class="space-y-1">
                                <div class="text-sm font-medium">
                                    Según sistema
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    {{ systemSummary.sales_count }} ventas ·
                                    Total ventas:
                                    {{ formatCurrency(systemSalesTotal) }}
                                </div>
                            </div>
                            <Button
                                variant="outline"
                                @click="applySystemSuggestion"
                            >
                                Usar valores del sistema
                            </Button>
                        </div>

                        <div class="grid gap-2 md:grid-cols-2">
                            <div
                                class="flex items-center justify-between rounded-md border bg-background px-3 py-2"
                            >
                                <span class="text-sm text-muted-foreground"
                                    >Total cobrado (pagos)</span
                                >
                                <span class="text-sm font-semibold">{{
                                    formatCurrency(systemPaymentsTotal)
                                }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between rounded-md border bg-background px-3 py-2"
                                :class="
                                    Math.abs(systemGap) < 0.01
                                        ? ''
                                        : 'border-destructive/40'
                                "
                            >
                                <span class="text-sm text-muted-foreground"
                                    >Diferencia pagos vs ventas</span
                                >
                                <span class="text-sm font-semibold">{{
                                    formatCurrency(systemGap)
                                }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-6">
                            <div
                                class="space-y-2 rounded-lg border bg-muted/50 p-4"
                            >
                                <div class="flex justify-between">
                                    <span class="text-sm text-muted-foreground"
                                        >Punto de Venta</span
                                    >
                                    <span class="font-medium">{{
                                        session.pos_config.name
                                    }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-muted-foreground"
                                        >Cajero</span
                                    >
                                    <span class="font-medium">{{
                                        session.user.name
                                    }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-muted-foreground"
                                        >Apertura</span
                                    >
                                    <span class="text-sm font-medium">
                                        {{ formatDateTime(session.opened_at) }}
                                    </span>
                                </div>
                                <div
                                    class="flex items-center justify-between border-t pt-2"
                                >
                                    <span class="text-sm text-muted-foreground"
                                        >Balance Inicial</span
                                    >
                                    <span class="text-lg font-semibold">
                                        {{
                                            formatCurrency(
                                                parseFloat(
                                                    session.opening_balance,
                                                ),
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="closing-balance"
                                    >Balance Final</Label
                                >
                                <div class="flex gap-2">
                                    <div class="relative flex-1">
                                        <DollarSign
                                            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                                        />
                                        <Input
                                            id="closing-balance"
                                            v-model="closingBalance"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            placeholder="0.00"
                                            class="pl-10 text-lg"
                                            autofocus
                                        />
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        @click="showCounterModal = true"
                                        title="Contar dinero"
                                    >
                                        <Calculator class="h-4 w-4" />
                                    </Button>
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    Total de efectivo contado al cierre (según
                                    arqueo)
                                </p>
                            </div>

                            <div
                                v-if="
                                    closingBalance &&
                                    parseFloat(closingBalance) > 0
                                "
                                class="rounded-lg p-4"
                                :class="
                                    difference >= 0
                                        ? 'bg-green-50 dark:bg-green-950/20'
                                        : 'bg-red-50 dark:bg-red-950/20'
                                "
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <TrendingUp
                                            v-if="difference >= 0"
                                            class="h-5 w-5 text-green-600 dark:text-green-400"
                                        />
                                        <TrendingDown
                                            v-else
                                            class="h-5 w-5 text-red-600 dark:text-red-400"
                                        />
                                        <span
                                            class="font-medium"
                                            :class="
                                                difference >= 0
                                                    ? 'text-green-900 dark:text-green-100'
                                                    : 'text-red-900 dark:text-red-100'
                                            "
                                        >
                                            {{
                                                difference >= 0
                                                    ? 'Ganancia'
                                                    : 'Pérdida'
                                            }}
                                        </span>
                                    </div>
                                    <span
                                        class="text-lg font-bold"
                                        :class="
                                            difference >= 0
                                                ? 'text-green-900 dark:text-green-100'
                                                : 'text-red-900 dark:text-red-100'
                                        "
                                    >
                                        {{
                                            formatCurrency(Math.abs(difference))
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="space-y-4">
                                <div>
                                    <h3 class="mb-2 font-semibold">
                                        Distribución por Método de Pago
                                    </h3>
                                    <p class="text-sm text-muted-foreground">
                                        La suma debe coincidir con el balance
                                        final
                                    </p>
                                </div>

                                <div class="space-y-3">
                                    <div
                                        v-for="method in paymentMethods"
                                        :key="method.id"
                                        class="grid grid-cols-12 items-center gap-3"
                                    >
                                        <div class="col-span-5 space-y-1">
                                            <Label
                                                :for="`payment-${method.id}`"
                                                >{{ method.name }}</Label
                                            >
                                            <button
                                                type="button"
                                                class="text-left text-xs text-muted-foreground underline-offset-4 hover:underline"
                                                @click="
                                                    applySystemAmountForMethod(
                                                        method.id,
                                                    )
                                                "
                                            >
                                                Sistema:
                                                {{
                                                    formatCurrency(
                                                        systemPaymentsByMethod.get(
                                                            method.id,
                                                        ) || 0,
                                                    )
                                                }}
                                            </button>
                                        </div>
                                        <div class="relative col-span-7">
                                            <DollarSign
                                                class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                                            />
                                            <Input
                                                :id="`payment-${method.id}`"
                                                v-model="payments[method.id]"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                placeholder="0.00"
                                                class="pl-10"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-2 rounded-lg border p-3">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span class="text-sm font-medium"
                                            >Total Métodos de Pago</span
                                        >
                                        <span class="font-semibold">{{
                                            formatCurrency(paymentsTotal)
                                        }}</span>
                                    </div>
                                    <div
                                        v-if="!isPaymentsValid"
                                        class="flex items-center gap-2 text-sm text-destructive"
                                    >
                                        <AlertCircle class="h-4 w-4" />
                                        <span
                                            >Falta cuadrar:
                                            {{
                                                formatCurrency(
                                                    Math.abs(
                                                        paymentsDifference,
                                                    ),
                                                )
                                            }}</span
                                        >
                                    </div>
                                    <div
                                        v-else-if="paymentsTotal > 0"
                                        class="flex items-center gap-2 text-sm text-green-600"
                                    >
                                        <span>✓ Total correcto</span>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="closing-note"
                                    >Nota de Cierre (Opcional)</Label
                                >
                                <Textarea
                                    id="closing-note"
                                    v-model="closingNote"
                                    placeholder="Ej: Ventas normales, sin incidentes..."
                                    rows="3"
                                    maxlength="1000"
                                />
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <div class="flex gap-3">
                        <Button
                            variant="outline"
                            class="flex-1"
                            @click="handleCancel"
                            :disabled="isSubmitting"
                        >
                            Cancelar
                        </Button>
                        <Button
                            variant="destructive"
                            class="flex-1"
                            @click="handleSubmit"
                            :disabled="isSubmitting"
                        >
                            {{ isSubmitting ? 'Cerrando...' : 'Cerrar Caja' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <CashCounterModal
            v-model:open="showCounterModal"
            @confirm="handleCounterConfirm"
        />
    </PosLayout>

    <div v-else class="bg-background p-6">
        <div class="mx-auto w-full max-w-[76.8rem] space-y-4">
            <div class="space-y-1">
                <h1 class="text-xl font-bold">Cierre de Caja</h1>
                <p class="text-sm text-muted-foreground">
                    Registra el balance final y distribuye por método de pago
                </p>
            </div>

            <Card>
                <CardContent class="space-y-6 p-6">
                    <div class="space-y-3 rounded-lg border bg-muted/30 p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div class="space-y-1">
                                <div class="text-sm font-medium">
                                    Según sistema
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    {{ systemSummary.sales_count }} ventas ·
                                    Total ventas:
                                    {{ formatCurrency(systemSalesTotal) }}
                                </div>
                            </div>
                            <Button
                                variant="outline"
                                @click="applySystemSuggestion"
                            >
                                Usar valores del sistema
                            </Button>
                        </div>

                        <div class="grid gap-2 md:grid-cols-2">
                            <div
                                class="flex items-center justify-between rounded-md border bg-background px-3 py-2"
                            >
                                <span class="text-sm text-muted-foreground"
                                    >Total cobrado (pagos)</span
                                >
                                <span class="text-sm font-semibold">{{
                                    formatCurrency(systemPaymentsTotal)
                                }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between rounded-md border bg-background px-3 py-2"
                                :class="
                                    Math.abs(systemGap) < 0.01
                                        ? ''
                                        : 'border-destructive/40'
                                "
                            >
                                <span class="text-sm text-muted-foreground"
                                    >Diferencia pagos vs ventas</span
                                >
                                <span class="text-sm font-semibold">{{
                                    formatCurrency(systemGap)
                                }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-6">
                            <div
                                class="space-y-2 rounded-lg border bg-muted/50 p-4"
                            >
                                <div class="flex justify-between">
                                    <span class="text-sm text-muted-foreground"
                                        >Punto de Venta</span
                                    >
                                    <span class="font-medium">{{
                                        session.pos_config.name
                                    }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-muted-foreground"
                                        >Cajero</span
                                    >
                                    <span class="font-medium">{{
                                        session.user.name
                                    }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-muted-foreground"
                                        >Apertura</span
                                    >
                                    <span class="text-sm font-medium">
                                        {{ formatDateTime(session.opened_at) }}
                                    </span>
                                </div>
                                <div
                                    class="flex items-center justify-between border-t pt-2"
                                >
                                    <span class="text-sm text-muted-foreground"
                                        >Balance Inicial</span
                                    >
                                    <span class="text-lg font-semibold">
                                        {{
                                            formatCurrency(
                                                parseFloat(
                                                    session.opening_balance,
                                                ),
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="closing-balance-embed"
                                    >Balance Final</Label
                                >
                                <div class="flex gap-2">
                                    <div class="relative flex-1">
                                        <DollarSign
                                            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                                        />
                                        <Input
                                            id="closing-balance-embed"
                                            v-model="closingBalance"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            placeholder="0.00"
                                            class="pl-10 text-lg"
                                            autofocus
                                        />
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        @click="showCounterModal = true"
                                        title="Contar dinero"
                                    >
                                        <Calculator class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>

                            <div
                                v-if="
                                    closingBalance &&
                                    parseFloat(closingBalance) > 0
                                "
                                class="rounded-lg p-4"
                                :class="
                                    difference >= 0
                                        ? 'bg-green-50 dark:bg-green-950/20'
                                        : 'bg-red-50 dark:bg-red-950/20'
                                "
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <TrendingUp
                                            v-if="difference >= 0"
                                            class="h-5 w-5 text-green-600 dark:text-green-400"
                                        />
                                        <TrendingDown
                                            v-else
                                            class="h-5 w-5 text-red-600 dark:text-red-400"
                                        />
                                        <span
                                            class="font-medium"
                                            :class="
                                                difference >= 0
                                                    ? 'text-green-900 dark:text-green-100'
                                                    : 'text-red-900 dark:text-red-100'
                                            "
                                        >
                                            {{
                                                difference >= 0
                                                    ? 'Ganancia'
                                                    : 'Pérdida'
                                            }}
                                        </span>
                                    </div>
                                    <span
                                        class="text-lg font-bold"
                                        :class="
                                            difference >= 0
                                                ? 'text-green-900 dark:text-green-100'
                                                : 'text-red-900 dark:text-red-100'
                                        "
                                    >
                                        {{
                                            formatCurrency(Math.abs(difference))
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="space-y-4">
                                <div>
                                    <h3 class="mb-2 font-semibold">
                                        Métodos de pago
                                    </h3>
                                    <p class="text-sm text-muted-foreground">
                                        La suma debe coincidir con el balance
                                        final
                                    </p>
                                </div>

                                <div class="space-y-3">
                                    <div
                                        v-for="method in paymentMethods"
                                        :key="method.id"
                                        class="grid grid-cols-12 items-center gap-3"
                                    >
                                        <div class="col-span-5 space-y-1">
                                            <Label
                                                :for="`payment-embed-${method.id}`"
                                                >{{ method.name }}</Label
                                            >
                                            <button
                                                type="button"
                                                class="text-left text-xs text-muted-foreground underline-offset-4 hover:underline"
                                                @click="
                                                    applySystemAmountForMethod(
                                                        method.id,
                                                    )
                                                "
                                            >
                                                Sistema:
                                                {{
                                                    formatCurrency(
                                                        systemPaymentsByMethod.get(
                                                            method.id,
                                                        ) || 0,
                                                    )
                                                }}
                                            </button>
                                        </div>
                                        <div class="relative col-span-7">
                                            <DollarSign
                                                class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                                            />
                                            <Input
                                                :id="`payment-embed-${method.id}`"
                                                v-model="payments[method.id]"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                placeholder="0.00"
                                                class="pl-10"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-2 rounded-lg border p-3">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span class="text-sm font-medium"
                                            >Total</span
                                        >
                                        <span class="font-semibold">{{
                                            formatCurrency(paymentsTotal)
                                        }}</span>
                                    </div>
                                    <div
                                        v-if="!isPaymentsValid"
                                        class="flex items-center gap-2 text-sm text-destructive"
                                    >
                                        <AlertCircle class="h-4 w-4" />
                                        <span
                                            >Falta cuadrar:
                                            {{
                                                formatCurrency(
                                                    Math.abs(
                                                        paymentsDifference,
                                                    ),
                                                )
                                            }}</span
                                        >
                                    </div>
                                    <div
                                        v-else-if="paymentsTotal > 0"
                                        class="flex items-center gap-2 text-sm text-green-600"
                                    >
                                        <span>✓ Total correcto</span>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="closing-note-embed"
                                    >Nota (Opcional)</Label
                                >
                                <Textarea
                                    id="closing-note-embed"
                                    v-model="closingNote"
                                    placeholder="Ej: Ventas normales, sin incidentes..."
                                    rows="3"
                                    maxlength="1000"
                                />
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <div class="flex gap-3">
                        <Button
                            variant="outline"
                            class="flex-1"
                            @click="handleCancel"
                            :disabled="isSubmitting"
                        >
                            Cancelar
                        </Button>
                        <Button
                            variant="destructive"
                            class="flex-1"
                            @click="handleSubmit"
                            :disabled="isSubmitting"
                        >
                            {{ isSubmitting ? 'Cerrando...' : 'Cerrar Caja' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <CashCounterModal
            v-model:open="showCounterModal"
            @confirm="handleCounterConfirm"
        />
    </div>
</template>
