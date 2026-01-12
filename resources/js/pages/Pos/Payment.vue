<script setup lang="ts">
import ClientSelectorModal from '@/components/pos/ClientSelectorModal.vue';
import ReceiptPreview from '@/components/pos/ReceiptPreview.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import PosLayout from '@/layouts/PosLayout.vue';
import { router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Banknote,
    Building2,
    CreditCard,
    DollarSign,
    Receipt,
    User,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

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

interface PosConfig {
    id: number;
    name: string;
    warehouse_id: number;
    company_id: number;
}

interface PosSession {
    id: number;
    user_id: number;
    pos_config_id: number;
    opened_at: string;
    status: string;
    user?: {
        name: string;
        email: string;
    };
    posConfig?: PosConfig;
}

interface Company {
    id: number;
    business_name: string;
    trade_name: string | null;
    ruc: string;
    address: string | null;
    phone: string | null;
    email: string | null;
    district: string | null;
    province: string | null;
    department: string | null;
    logo_url: string | null;
}

interface Props {
    session: PosSession;
    journals: Journal[];
    paymentMethods: PaymentMethod[];
    cart: CartItem[];
    client: Client | null;
    total: number;
    customers: Client[];
    company: Company;
}

const props = defineProps<Props>();

// DEBUG: Ver qué llega en props
console.log('🔍 [Payment Debug] Props recibidos:', {
    session: props.session,
    'session.pos_config': (props.session as any)?.pos_config,
    'session.pos_config.name': (props.session as any)?.pos_config?.name,
    company: props.company,
});

// Clients from API (hybrid: initialized with server data, refreshable)
const clients = ref<Client[]>(props.customers);

// State
const selectedJournalId = ref<string | undefined>(
    props.journals[0]?.id.toString(),
);
const paymentAmounts = ref<Record<number, string>>({});
const isProcessing = ref(false);
const showClientModal = ref(false);

// Map props.client to Client format if it exists, otherwise find in customers by id
const currentClient = ref<Client | null>(
    props.client
        ? clients.value.find((c) => c.id === props.client?.id) || null
        : null,
);

// Initialize payment amounts as empty for better UX
props.paymentMethods.forEach((method) => {
    paymentAmounts.value[method.id] = '';
});

// Computed
const totalEntered = computed(() => {
    return Object.values(paymentAmounts.value).reduce(
        (sum, amount) => sum + parseFloat(amount || '0'),
        0,
    );
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

//Computed Properties
const selectedJournal = computed(() => {
    if (!selectedJournalId.value) return undefined;
    return (
        props.journals.find(
            (j) => j.id.toString() === selectedJournalId.value,
        ) || undefined
    );
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
        paymentAmounts.value[methodId] =
            parts[0] + '.' + parts.slice(1).join('');
    } else {
        paymentAmounts.value[methodId] = sanitized;
    }
};

const handleFocus = (event: Event) => {
    const input = event.target as HTMLInputElement;
    // Select all text on focus for easy replacement
    input.select();
};

const printReceipt = (receiptHtml: string, styles: string) => {
    // Create a hidden iframe for printing
    const iframe = document.createElement('iframe');
    iframe.style.position = 'fixed';
    iframe.style.width = '0px';
    iframe.style.height = '0px';
    iframe.style.border = 'none';
    document.body.appendChild(iframe);

    if (!iframe.contentWindow) {
        document.body.removeChild(iframe);
        console.error('Print error: No iframe content window');
        return;
    }

    const doc = iframe.contentWindow.document;

    doc.open();
    doc.write(`
        <html>
        <head>
            <title>Recibo de Venta</title>
            ${styles}
            <style>
                body {
                    margin: 0;
                    background: white;
                    display: flex;
                    justify-content: center;
                }
                .receipt-printable {
                    box-shadow: none !important;
                    border: none !important;
                    width: 80mm !important;
                    margin: 0 !important;
                }
            </style>
        </head>
        <body>
            ${receiptHtml}
        </body>
        </html>
    `);
    doc.close();

    // Wait for content to load and styles to apply
    setTimeout(() => {
        try {
            iframe.contentWindow?.focus();
            iframe.contentWindow?.print();
        } catch (e) {
            console.error('Print error:', e);
            alert('Error al intentar imprimir. Por favor revise la consola.');
        }

        // Remove iframe after printing (with a delay to ensure print dialog opened)
        setTimeout(() => {
            document.body.removeChild(iframe);
        }, 1000);
    }, 500);
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

    // 1. Capture Receipt HTML and Styles NOW, while the component is mounted
    const receiptElement = document.querySelector('.receipt-printable');
    if (!receiptElement) {
        alert(
            'Error: No se pudo generar el recibo para impresión (Elemento no encontrado).',
        );
        return;
    }
    const receiptHtml = receiptElement.outerHTML;

    // Capture styles from current document to ensure they are available in the iframe
    // even if the page navigates away
    const styles = Array.from(
        document.querySelectorAll('style, link[rel="stylesheet"]'),
    )
        .map((style) => style.outerHTML)
        .join('');

    isProcessing.value = true;

    // Prepare payment data
    const payments = Object.entries(paymentAmounts.value)
        .filter(([, amount]) => parseFloat(amount || '0') > 0)
        .map(([methodId, amount]) => ({
            payment_method_id: parseInt(methodId),
            amount: parseFloat(amount),
        }));

    const payloadData = {
        journal_id: parseInt(selectedJournalId.value),
        cart: JSON.stringify(props.cart),
        client_id: props.client?.id || null,
        total: props.total,
        payments: JSON.stringify(payments),
    };

    router.post(`/pos/${props.session.id}/process`, payloadData, {
        onSuccess: () => {
            // Clear sessionStorage on successful payment
            sessionStorage.removeItem(`pos_cart_${props.session.id}`);
            sessionStorage.removeItem(`pos_client_${props.session.id}`);

            // Print receipt using the captured HTML
            printReceipt(receiptHtml, styles);
        },
        onError: (errors) => {
            console.error('Error al procesar venta:', errors);
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
/* No styles needed for print here as it's handled by iframe injection */
</style>

<template>
    <PosLayout title="Procesar Pago">
        <template #header-actions>
            <Button variant="ghost" @click="handleBack">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Volver al POS
            </Button>
        </template>

        <div class="space-y-4">
            <!-- Header Summary Bar -->
            <div
                class="rounded-lg bg-gradient-to-r from-purple-600 to-purple-700 p-4 text-white"
            >
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <Receipt class="h-6 w-6" />
                        <div>
                            <p class="text-sm opacity-90">Total a Pagar</p>
                            <p class="text-3xl font-bold">
                                {{ formatCurrency(total) }}
                            </p>
                        </div>
                    </div>
                    <!-- Payment Button -->
                    <Button
                        size="lg"
                        class="h-14 bg-white px-8 font-bold text-purple-700 hover:bg-purple-50"
                        @click="handleProcessPayment"
                        :disabled="!isPaymentComplete || isProcessing"
                    >
                        {{
                            isProcessing
                                ? 'Procesando...'
                                : 'CONFIRMAR Y PROCESAR PAGO →'
                        }}
                    </Button>
                </div>
            </div>

            <!-- Main Layout: 67% Left, 33% Right -->
            <div class="grid h-[calc(100vh-180px)] grid-cols-[2fr_1fr] gap-4">
                <!-- LEFT SECTION: Configuration + Payment Methods -->
                <Card class="flex h-full flex-col">
                    <CardContent class="flex-1 space-y-4 overflow-y-auto p-6">
                        <!-- 1. Diarios + Cliente (Same Row) -->
                        <div
                            class="grid grid-cols-[1fr_auto] items-start gap-4"
                        >
                            <!-- Left: Journals (Touch-Friendly) -->
                            <div class="space-y-3">
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        v-for="journal in journals"
                                        :key="journal.id"
                                        @click="
                                            selectedJournalId =
                                                journal.id.toString()
                                        "
                                        :variant="
                                            selectedJournalId ===
                                            journal.id.toString()
                                                ? 'default'
                                                : 'outline'
                                        "
                                        size="lg"
                                        class="h-12 px-6 font-mono text-base font-semibold"
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
                                    :class="
                                        currentClient
                                            ? 'min-w-[200px] bg-blue-100 font-semibold text-blue-700 hover:bg-blue-200'
                                            : 'min-w-[200px] bg-blue-50 font-semibold text-blue-600 hover:bg-blue-100'
                                    "
                                    @click="openClientModal"
                                >
                                    <User
                                        class="h-4 w-4"
                                        :class="
                                            currentClient ? 'mr-1.5' : 'mr-2'
                                        "
                                    />
                                    <span
                                        v-if="currentClient"
                                        class="text-sm font-bold"
                                        >{{ getClientInitials() }}</span
                                    >
                                    <span v-else>Seleccionar Cliente</span>
                                </Button>
                            </div>
                        </div>

                        <!-- 2. Payment Section: Methods (Left 65%) + Summary Sidebar (Right 35%) -->
                        <div
                            class="grid grid-cols-[65fr_35fr] gap-6 border-t-2 pt-3"
                        >
                            <!-- Left: Payment Methods List -->
                            <div class="space-y-3">
                                <div
                                    v-for="method in paymentMethods"
                                    :key="method.id"
                                    class="flex items-center gap-3"
                                >
                                    <label
                                        class="flex w-24 flex-shrink-0 items-center gap-2 text-sm font-medium"
                                    >
                                        <component
                                            :is="
                                                getPaymentMethodIcon(
                                                    method.name,
                                                )
                                            "
                                            class="h-4 w-4"
                                        />
                                        <span>{{ method.name }}</span>
                                    </label>
                                    <div class="relative flex-1">
                                        <span
                                            class="absolute top-1/2 left-3 -translate-y-1/2 text-sm font-medium text-muted-foreground"
                                            >S/</span
                                        >
                                        <Input
                                            v-model="paymentAmounts[method.id]"
                                            @input="
                                                (e: any) =>
                                                    handlePaymentInput(
                                                        method.id,
                                                        e,
                                                    )
                                            "
                                            @focus="handleFocus"
                                            type="text"
                                            inputmode="decimal"
                                            placeholder="0.00"
                                            class="h-10 pl-10 text-base font-medium"
                                        />
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-10 px-4"
                                        @click="
                                            () => {
                                                paymentAmounts[method.id] =
                                                    total.toFixed(2);
                                                paymentMethods.forEach((m) => {
                                                    if (m.id !== method.id)
                                                        paymentAmounts[m.id] =
                                                            '';
                                                });
                                            }
                                        "
                                    >
                                        Todo
                                    </Button>
                                </div>
                            </div>

                            <!-- Right: Summary Sidebar -->
                            <div class="space-y-4">
                                <!-- Restante Card -->
                                <div
                                    class="rounded-lg border-2 border-orange-200 bg-orange-50 p-4"
                                >
                                    <p
                                        class="mb-1 text-xs font-semibold tracking-wide text-orange-700 uppercase"
                                    >
                                        Restante
                                    </p>
                                    <p
                                        class="text-3xl font-bold"
                                        :class="
                                            remaining > 0
                                                ? 'text-orange-600'
                                                : 'text-green-600'
                                        "
                                    >
                                        {{ formatCurrency(remaining) }}
                                    </p>
                                </div>

                                <!-- Cambio Card -->
                                <div
                                    class="rounded-lg border-2 p-4"
                                    :class="
                                        change > 0
                                            ? 'border-green-200 bg-green-50'
                                            : 'border-gray-200 bg-gray-50'
                                    "
                                >
                                    <p
                                        class="mb-1 text-xs font-semibold tracking-wide uppercase"
                                        :class="
                                            change > 0
                                                ? 'text-green-700'
                                                : 'text-gray-600'
                                        "
                                    >
                                        Cambio
                                    </p>
                                    <p
                                        class="text-3xl font-bold"
                                        :class="
                                            change > 0
                                                ? 'text-green-600'
                                                : 'text-gray-500'
                                        "
                                    >
                                        {{ formatCurrency(change) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- RIGHT SECTION: Ticket Preview -->
                <div>
                    <ReceiptPreview
                        :company="company"
                        :session="session"
                        :selected-journal="selectedJournal"
                        :cart="cart"
                        :total="total"
                        :client="currentClient"
                        :payment-methods="paymentMethods"
                        :payment-amounts="paymentAmounts"
                    />
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
