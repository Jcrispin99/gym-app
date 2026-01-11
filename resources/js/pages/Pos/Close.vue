<script setup lang="ts">
import PosLayout from '@/layouts/PosLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Separator } from '@/components/ui/separator';
import CashCounterModal from '@/components/pos/CashCounterModal.vue';
import { router } from '@inertiajs/vue3';
import { ArrowLeft, DollarSign, TrendingUp, TrendingDown, Calculator, AlertCircle } from 'lucide-vue-next';
import { ref, computed } from 'vue';

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

interface Props {
    session: PosSession;
    paymentMethods: PaymentMethod[];
}

const props = defineProps<Props>();

const closingBalance = ref<string>('0.00');
const closingNote = ref<string>('');
const isSubmitting = ref(false);
const showCounterModal = ref(false);

// Payment methods distribution
const payments = ref<Record<number, string>>(
    props.paymentMethods.reduce((acc, method) => {
        acc[method.id] = '0.00';
        return acc;
    }, {} as Record<number, string>)
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

const handleSubmit = () => {
    if (isSubmitting.value) return;
    if (!isPaymentsValid.value) {
        return;
    }
    
    isSubmitting.value = true;
    
    const paymentsArray = props.paymentMethods.map(method => ({
        payment_method_id: method.id,
        amount: parseFloat(payments.value[method.id] || '0'),
    })).filter(p => p.amount > 0);
    
    router.post(`/pos/${props.session.id}/close`, {
        closing_balance: parseFloat(closingBalance.value),
        closing_note: closingNote.value || null,
        payments: paymentsArray,
    }, {
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const handleCancel = () => {
    router.visit(`/pos/${props.session.id}`);
};
</script>

<template>
    <PosLayout :title="`Cierre de Caja - ${session.pos_config.name}`">
        <div class="max-w-2xl mx-auto mt-8">
            <!-- Back Button -->
            <Button
                variant="ghost"
                class="mb-4"
                @click="handleCancel"
            >
                <ArrowLeft class="h-4 w-4 mr-2" />
                Volver al Dashboard
            </Button>

            <!-- Closing Form -->
            <Card>
                <CardHeader>
                    <CardTitle>Cierre de Caja</CardTitle>
                    <CardDescription>
                        Ingresa el balance final y distribuye los montos por método de pago
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- Session Info -->
                    <div class="rounded-lg border bg-muted/50 p-4 space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-muted-foreground">Punto de Venta</span>
                            <span class="font-medium">{{ session.pos_config.name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-muted-foreground">Cajero</span>
                            <span class="font-medium">{{ session.user.name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-muted-foreground">Apertura</span>
                            <span class="font-medium text-sm">
                                {{ formatDateTime(session.opened_at) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center pt-2 border-t">
                            <span class="text-sm text-muted-foreground">Balance Inicial</span>
                            <span class="font-semibold text-lg">
                                {{ formatCurrency(parseFloat(session.opening_balance)) }}
                            </span>
                        </div>
                    </div>

                    <!-- Closing Balance Input -->
                    <div class="space-y-2">
                        <Label for="closing-balance">Balance Final</Label>
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <DollarSign class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
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
                            Ingresa el dinero total con el que cuentas al final del día
                        </p>
                    </div>

                    <!-- Difference Display -->
                    <div v-if="closingBalance && parseFloat(closingBalance) > 0" 
                         class="rounded-lg p-4"
                         :class="difference >= 0 ? 'bg-green-50 dark:bg-green-950/20' : 'bg-red-50 dark:bg-red-950/20'"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <TrendingUp v-if="difference >= 0" class="h-5 w-5 text-green-600 dark:text-green-400" />
                                <TrendingDown v-else class="h-5 w-5 text-red-600 dark:text-red-400" />
                                <span class="font-medium"
                                      :class="difference >= 0 ? 'text-green-900 dark:text-green-100' : 'text-red-900 dark:text-red-100'"
                                >
                                    {{ difference >= 0 ? 'Ganancia' : 'Pérdida' }}
                                </span>
                            </div>
                            <span class="text-lg font-bold"
                                  :class="difference >= 0 ? 'text-green-900 dark:text-green-100' : 'text-red-900 dark:text-red-100'"
                            >
                                {{ formatCurrency(Math.abs(difference)) }}
                            </span>
                        </div>
                    </div>

                    <Separator />

                    <!-- Payment Methods Distribution -->
                    <div class="space-y-4">
                        <div>
                            <h3 class="font-semibold mb-2">Distribución por Método de Pago</h3>
                            <p class="text-sm text-muted-foreground">
                                Ingresa el monto recibido por cada método de pago
                            </p>
                        </div>

                        <div class="space-y-3">
                            <div v-for="method in paymentMethods" :key="method.id" class="flex items-center gap-3">
                                <Label :for="`payment-${method.id}`" class="w-24">{{ method.name }}</Label>
                                <div class="relative flex-1">
                                    <DollarSign class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
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

                        <!-- Payments Total -->
                        <div class="rounded-lg border p-3 space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium">Total Métodos de Pago</span>
                                <span class="font-semibold">{{ formatCurrency(paymentsTotal) }}</span>
                            </div>
                            <div v-if="!isPaymentsValid" class="flex items-center gap-2 text-destructive text-sm">
                                <AlertCircle class="h-4 w-4" />
                                <span>La suma debe coincidir con el balance final (diferencia: {{ formatCurrency(Math.abs(paymentsDifference)) }})</span>
                            </div>
                            <div v-else-if="paymentsTotal > 0" class="flex items-center gap-2 text-green-600 text-sm">
                                <span>✓ Total correcto</span>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <!-- Closing Note -->
                    <div class="space-y-2">
                        <Label for="closing-note">Nota de Cierre (Opcional)</Label>
                        <Textarea
                            id="closing-note"
                            v-model="closingNote"
                            placeholder="Ej: Ventas normales, sin incidentes..."
                            rows="3"
                            maxlength="1000"
                        />
                    </div>

                    <!-- Actions -->
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
                            :disabled="isSubmitting || !isPaymentsValid"
                        >
                            {{ isSubmitting ? 'Cerrando...' : 'Cerrar Caja' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Cash Counter Modal -->
        <CashCounterModal
            v-model:open="showCounterModal"
            @confirm="handleCounterConfirm"
        />
    </PosLayout>
</template>
