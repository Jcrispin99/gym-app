<script setup lang="ts">
import PosLayout from '@/layouts/PosLayout.vue';
import ClientSelectorModal from '@/components/Pos/ClientSelectorModal.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { 
    ArrowLeft, 
    User,
    Receipt,
    DollarSign,
    CreditCard,
    Banknote,
    Building2
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

// Types
interface CartItem {
    product_id: number;
    name: string;
    qty: number;
    price: number;
    subtotal: number;
}

interface Client {
    id: number;
    name: string;
    dni?: string;
    email?: string;
    phone?: string;
}

interface Journal {
    id: number;
    name: string;
    code: string;
    type: string;
}

interface PaymentMethod {
    id: number;
    name: string;
    is_active: boolean;
}

interface PosSession {
    id: number;
    user_id: number;
    pos_config_id: number;
    opened_at: string;
    status: string;
}

interface Props {
    session: PosSession;
    journals: Journal[];
    paymentMethods: PaymentMethod[];
    cart: CartItem[];
    client: Client | null;
    total: number;
    customers: Client[]; // Initial customers from server
}

const props = defineProps<Props>();

// Clients from API (hybrid: initialized with server data, refreshable)
const clients = ref<Client[]>(props.customers);

// State
const selectedJournalId = ref<string | undefined>(props.journals[0]?.id.toString());
const paymentAmounts = ref<Record<number, string>>({});
const isProcessing = ref(false);
const showClientModal = ref(false);

// Map props.client to Client format if it exists, otherwise find in customers by id
const currentClient = ref<Client | null>(
    props.client 
        ? clients.value.find(c => c.id === props.client?.id) || null
        : null
);

// Initialize payment amounts as empty for better UX
props.paymentMethods.forEach(method => {
    paymentAmounts.value[method.id] = '';
});

// Computed
const totalEntered = computed(() => {
    return Object.values(paymentAmounts.value)
        .reduce((sum, amount) => sum + parseFloat(amount || '0'), 0);
});

const remaining = computed(() => {
    return Math.max(0, props.total - totalEntered.value);
});

const change = computed(() => {
    return Math.max(0, totalEntered.value - props.total);
});

const isPaymentComplete = computed(() => {
    return Math.abs(totalEntered.value - props.total) < 0.01;
});

const activePayments = computed(() => {
    return props.paymentMethods.filter(method => {
        const amount = parseFloat(paymentAmounts.value[method.id] || '0');
        return amount > 0;
    });
});

//Computed Properties
const selectedJournal = computed(() => {
    if (!selectedJournalId.value) return null;
    return props.journals.find(j => j.id.toString() === selectedJournalId.value) || null;
});

// Methods
const formatCurrency = (value: number): string => {
    return `S/ ${value.toFixed(2)}`;
};

const handleBack = () => {
    router.visit(`/pos/${props.session.id}`);
};

const handlePaymentInput = (methodId: number, event: Event) => {
    const input = event.target as HTMLInputElement;
    // Only allow numbers and decimals
    const sanitized = input.value.replace(/[^0-9.]/g, '');
    // Prevent multiple decimal points
    const parts = sanitized.split('.');
    if (parts.length > 2) {
        paymentAmounts.value[methodId] = parts[0] + '.' + parts.slice(1).join('');
    } else {
        paymentAmounts.value[methodId] = sanitized;
    }
};

const handleFocus = (event: Event) => {
    const input = event.target as HTMLInputElement;
    // Select all text on focus for easy replacement
    input.select();
};

const printReceipt = () => {
    // Trigger browser print dialog
    window.print();
};

const handleProcessPayment = () => {
    if (!isPaymentComplete.value) {
        alert('El pago debe completarse antes de procesar');
        return;
    }

    if (!selectedJournalId.value) {
        alert('Debe seleccionar un diario');
        return;
    }

    isProcessing.value = true;

    // Prepare payment data
    const payments = Object.entries(paymentAmounts.value)
        .filter(([, amount]) => parseFloat(amount || '0') > 0)
        .map(([methodId, amount]) => ({
            payment_method_id: parseInt(methodId),
            amount: parseFloat(amount)
        }));

    router.post(`/pos/${props.session.id}/process`, {
        journal_id: parseInt(selectedJournalId.value),
        cart: JSON.stringify(props.cart),
        client_id: props.client?.id || null,
        total: props.total,
        payments: JSON.stringify(payments),
    }, {
        onSuccess: () => {
            // Clear sessionStorage on successful payment
            sessionStorage.removeItem(`pos_cart_${props.session.id}`);
            sessionStorage.removeItem(`pos_client_${props.session.id}`);
            
            // Print receipt before redirecting
            printReceipt();
        },
        onFinish: () => {
            isProcessing.value = false;
        },
    });
};

const getClientInitials = () => {
    if (!currentClient.value) return '?';
    const names = currentClient.value.name.split(' ');
    if (names.length >= 2) {
        return (names[0][0] + names[names.length - 1][0]).toUpperCase();
    }
    return currentClient.value.name.substring(0, 2).toUpperCase();
};

const getCurrentDateTime = () => {
    const now = new Date();
    return now.toLocaleString('es-PE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

// Refresh clients from API
const refreshClients = async () => {
    try {
        const response = await fetch('/api/pos/customers');
        const data = await response.json();
        clients.value = data;
    } catch (error) {
        console.error('Error refreshing clients:', error);
    }
};

// Initial data comes from server props, refreshClients() available if needed
// No need for onMounted fetch

const getPaymentMethodIcon = (name: string) => {
    const lower = name.toLowerCase();
    if (lower.includes('efectivo') || lower.includes('cash')) return Banknote;
    if (lower.includes('tarjeta') || lower.includes('card')) return CreditCard;
    if (lower.includes('transfer') || lower.includes('banco')) return Building2;
    return DollarSign;
};

const openClientModal = () => {
    showClientModal.value = true;
};

const selectClient = (client: Client) => {
    currentClient.value = client;
};

const clearClient = () => {
    currentClient.value = null;
};
</script>

<style scoped>
@media print {
    /* Hide everything except the receipt */
    body * {
        visibility: hidden;
    }
    
    /* Show only the receipt and its children */
    .receipt-printable,
    .receipt-printable * {
        visibility: visible;
    }
    
    /* Position receipt at top left for printing */
    .receipt-printable {
        position: absolute;
        left: 0;
        top: 0;
        width: 80mm;
        margin: 0;
        padding: 0;
    }
    
    /* Remove shadows and borders for clean print */
    .receipt-printable {
        box-shadow: none !important;
        border: none !important;
    }
    
    /* Set page size for thermal printer */
    @page {
        size: 80mm auto;
        margin: 0;
    }
}
</style>

<template>
    <PosLayout title="Procesar Pago">
        <template #header-actions>
            <Button variant="ghost" @click="handleBack">
                <ArrowLeft class="h-4 w-4 mr-2" />
                Volver al POS
            </Button>
        </template>

        <div class="space-y-4">
            <!-- Header Summary Bar -->
            <div class="bg-gradient-to-r from-purple-600 to-purple-700 text-white rounded-lg p-4">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <Receipt class="h-6 w-6" />
                        <div>
                            <p class="text-sm opacity-90">Total a Pagar</p>
                            <p class="text-3xl font-bold">{{ formatCurrency(total) }}</p>
                        </div>
                    </div>
                    <!-- Payment Button -->
                    <Button
                        size="lg"
                        class="bg-white text-purple-700 hover:bg-purple-50 font-bold px-8 h-14"
                        @click="handleProcessPayment"
                        :disabled="!isPaymentComplete || isProcessing"
                    >
                        {{ isProcessing ? 'Procesando...' : 'CONFIRMAR Y PROCESAR PAGO →' }}
                    </Button>
                </div>
            </div>

            <!-- Main Layout: 67% Left, 33% Right -->
            <div class="grid grid-cols-[2fr_1fr] gap-4 h-[calc(100vh-180px)]">
                <!-- LEFT SECTION: Configuration + Payment Methods -->
                <Card class="h-full flex flex-col">
                    <CardContent class="space-y-4 flex-1 overflow-y-auto p-6">
                        <!-- 1. Diarios + Cliente (Same Row) -->
                        <div class="grid grid-cols-[1fr_auto] gap-4 items-start">
                            <!-- Left: Journals (Touch-Friendly) -->
                            <div class="space-y-3">
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        v-for="journal in journals"
                                        :key="journal.id"
                                        @click="selectedJournalId = journal.id.toString()"
                                        :variant="selectedJournalId === journal.id.toString() ? 'default' : 'outline'"
                                        size="lg"
                                        class="font-mono font-semibold text-base h-12 px-6"
                                    >
                                        {{ journal.code }}
                                    </Button>
                                </div>
                            </div>

                            <!-- Right: Client Selector -->
                            <div class="space-y-3">
                                <Button 
                                    variant="outline" 
                                    size="lg"
                                    :class="currentClient ? 'bg-blue-100 hover:bg-blue-200 text-blue-700 font-semibold min-w-[200px]' : 'bg-blue-50 hover:bg-blue-100 text-blue-600 font-semibold min-w-[200px]'"
                                    @click="openClientModal"
                                >
                                    <User class="h-4 w-4" :class="currentClient ? 'mr-1.5' : 'mr-2'" />
                                    <span v-if="currentClient" class="text-sm font-bold">{{ getClientInitials() }}</span>
                                    <span v-else>Seleccionar Cliente</span>
                                </Button>
                            </div>
                        </div>

                        <!-- 2. Payment Section: Methods (Left 65%) + Summary Sidebar (Right 35%) -->
                        <div class="grid grid-cols-[65fr_35fr] gap-6 pt-3 border-t-2">
                            <!-- Left: Payment Methods List -->
                            <div class="space-y-3">
                                <div 
                                    v-for="method in paymentMethods" 
                                    :key="method.id"
                                    class="flex items-center gap-3"
                                >
                                    <label class="text-sm font-medium flex items-center gap-2 w-24 flex-shrink-0">
                                        <component :is="getPaymentMethodIcon(method.name)" class="h-4 w-4" />
                                        <span>{{ method.name }}</span>
                                    </label>
                                    <div class="relative flex-1">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground font-medium">S/</span>
                                        <Input
                                            v-model="paymentAmounts[method.id]"
                                            @input="(e: any) => handlePaymentInput(method.id, e)"
                                            @focus="handleFocus"
                                            type="text"
                                            inputmode="decimal"
                                            placeholder="0.00"
                                            class="pl-10 h-10 text-base font-medium"
                                        />
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-10 px-4"
                                        @click="() => {
                                            paymentAmounts[method.id] = total.toFixed(2);
                                            paymentMethods.forEach(m => {
                                                if (m.id !== method.id) paymentAmounts[m.id] = '';
                                            });
                                        }"
                                    >
                                        Todo
                                    </Button>
                                </div>
                            </div>

                            <!-- Right: Summary Sidebar -->
                            <div class="space-y-4">
                                <!-- Restante Card -->
                                <div class="p-4 bg-orange-50 rounded-lg border-2 border-orange-200">
                                    <p class="text-xs font-semibold text-orange-700 uppercase tracking-wide mb-1">Restante</p>
                                    <p class="text-3xl font-bold" :class="remaining > 0 ? 'text-orange-600' : 'text-green-600'">
                                        {{ formatCurrency(remaining) }}
                                    </p>
                                </div>

                                <!-- Cambio Card -->
                                <div class="p-4 rounded-lg border-2" :class="change > 0 ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200'">
                                    <p class="text-xs font-semibold uppercase tracking-wide mb-1" :class="change > 0 ? 'text-green-700' : 'text-gray-600'">Cambio</p>
                                    <p class="text-3xl font-bold" :class="change > 0 ? 'text-green-600' : 'text-gray-500'">
                                        {{ formatCurrency(change) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- RIGHT SECTION: Ticket Preview -->
                <div>
                    <div class="receipt-printable bg-white border border-black w-[80mm] min-h-full text-xs">
                        <!-- 1. ENCABEZADO COMPLETO -->
                        <div class="px-6 py-4 border-b space-y-3">
                            <!-- Logo y Datos Empresa -->
                            <div class="text-center space-y-1">
                                <!-- Logo Placeholder -->
                                <div class="w-20 h-20 bg-gray-200 rounded-full mx-auto flex items-center justify-center text-gray-500 text-[10px]">
                                    LOGO
                                </div>
                                
                                <div class="text-sm font-semibold">GYM FITNESS CENTER</div>
                                <div class="text-xs text-gray-700">RUC: 20123456789</div>
                                <div class="text-xs text-gray-500">Av. Principal 123 - Lima</div>
                                
                                <!-- Tipo y Número de Comprobante -->
                                <div class="text-xs font-medium mt-2">Boleta de Venta</div>
                                <div class="text-base font-bold">
                                    B001 <span class="text-gray-400 mx-1">-</span> 00001234
                                </div>
                                
                                <!-- Sesión POS -->
                                <div class="text-xs text-gray-600">Sesión POS #{{ session?.id || '-' }}</div>
                            </div>

                            <!-- Detalles de Emisión -->
                            <div class="text-xs space-y-1 mt-2">
                                <div class="flex justify-between">
                                    <span class="font-semibold text-gray-800">Fecha de emisión:</span>
                                    <span class="text-gray-700">{{ getCurrentDateTime() }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-semibold text-gray-800">Punto de venta:</span>
                                    <span class="text-gray-700">{{ selectedJournal?.code || '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-semibold text-gray-800">Vendedor:</span>
                                    <span class="text-gray-700">{{ session?.user?.name || '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-semibold text-gray-800">Condición:</span>
                                    <span class="text-gray-700">Contado</span>
                                </div>
                            </div>

                            <!-- Separador -->
                            <div class="border-t border-gray-200 my-2"></div>

                            <!-- Información del Cliente -->
                            <div class="text-xs space-y-1">
                                <div class="flex justify-between">
                                    <span class="font-semibold text-gray-800">Cliente:</span>
                                    <span class="text-gray-700">{{ currentClient?.name || '—' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-semibold text-gray-800">Documento:</span>
                                    <span class="text-gray-700">{{ currentClient?.dni || '—' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 2. ITEMS (TABLA) -->
                        <div class="px-6 py-2">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="border-b">
                                        <th class="text-left py-1">Producto</th>
                                        <th class="text-right py-1">Cant.</th>
                                        <th class="text-right py-1">Precio</th>
                                        <th class="text-right py-1">Importe</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr 
                                        v-for="item in cart" 
                                        :key="item.product_id"
                                        class="border-b"
                                    >
                                        <td class="py-1 pr-2 truncate">{{ item.name }}</td>
                                        <td class="py-1 text-right">{{ item.qty.toFixed(2) }}</td>
                                        <td class="py-1 text-right">{{ item.price.toFixed(2) }}</td>
                                        <td class="py-1 text-right font-medium">{{ item.subtotal.toFixed(2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- 3. TOTALES DETALLADOS -->
                        <div class="px-6 py-3 text-xs">
                            <div class="flex justify-end">
                                <div class="w-full space-y-1">
                                    <div class="flex justify-between">
                                        <span>Subtotal</span>
                                        <span>{{ (total / 1.18).toFixed(2) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>IGV (18%)</span>
                                        <span>{{ (total - (total / 1.18)).toFixed(2) }}</span>
                                    </div>
                                    <div class="border-t pt-2 flex justify-between font-semibold text-sm">
                                        <span>Total</span>
                                        <span>{{ formatCurrency(total) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 4. PAGOS -->
                        <div v-if="activePayments.length > 0" class="px-6 py-3 text-xs">
                            <div class="text-gray-600 mb-1 font-medium">Pagos</div>
                            <ul class="space-y-1">
                                <li 
                                    v-for="payment in activePayments" 
                                    :key="payment.id"
                                    class="flex justify-between"
                                >
                                    <span class="truncate">{{ payment.name }}</span>
                                    <span>{{ formatCurrency(parseFloat(paymentAmounts[payment.id] || '0')) }}</span>
                                </li>
                            </ul>
                            
                            <div class="mt-2 flex justify-between border-t pt-2 font-medium">
                                <span>Total pagado</span>
                                <span>{{ formatCurrency(totalEntered) }}</span>
                            </div>
                            
                            <div v-if="change > 0" class="flex justify-between text-gray-600 mt-1">
                                <span>Vuelto</span>
                                <span>{{ formatCurrency(change) }}</span>
                            </div>
                        </div>

                        <!-- 5. PIE DE PÁGINA -->
                        <div class="px-6 py-3 text-center text-xs text-gray-600 italic border-t">
                            Gracias por su compra.<br>
                            Conserve este comprobante.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Client Selector Modal -->
        <ClientSelectorModal
            v-model:open="showClientModal"
            :selected-client="currentClient"
            :clients="clients"
            @select="selectClient"
            @clear="clearClient"
        />
    </PosLayout>
</template>
