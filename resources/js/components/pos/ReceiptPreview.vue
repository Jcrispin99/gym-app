<script setup lang="ts">
import { computed } from 'vue';

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
    user?: {
        name: string;
        email: string;
    };
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
    company: Company;
    session: PosSession;
    selectedJournal?: Journal;
    cart: CartItem[];
    total: number;
    client?: Client | null;
    paymentMethods: PaymentMethod[];
    paymentAmounts: Record<number, string | number>;
}

const props = defineProps<Props>();

// Computed
const getCurrentDateTime = () => {
    const now = new Date();
    return now.toLocaleString('es-PE', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
};

const formatCurrency = (value: number): string => {
    return `S/ ${value.toFixed(2)}`;
};

const activePayments = computed(() => {
    return props.paymentMethods.filter(method => {
        const amount = props.paymentAmounts[method.id];
        return amount && parseFloat(String(amount)) > 0;
    });
});
</script>

<template>
    <div class="receipt-printable bg-white border border-black w-[80mm] min-h-full text-xs">
        <!-- 1. ENCABEZADO COMPLETO -->
        <div class="px-6 py-4 border-b space-y-3">
            <!-- Logo y Datos Empresa -->
            <div class="text-center space-y-1">
                <!-- Logo -->
                <img 
                    v-if="company.logo_url" 
                    :src="company.logo_url" 
                    alt="Logo"
                    class="h-20 mx-auto mb-2"
                />
                <div v-else class="w-20 h-20 bg-gray-200 rounded-full mx-auto flex items-center justify-center text-gray-500 text-[10px]">
                    LOGO
                </div>
                
                <!-- Nombre del Negocio -->
                <div class="text-sm font-semibold">
                    {{ company.trade_name || company.business_name }}
                </div>
                
                <!-- RUC -->
                <div class="text-xs text-gray-700">RUC: {{ company.ruc }}</div>
                
                <!-- Dirección -->
                <div class="text-xs text-gray-500" v-if="company.address">
                    {{ company.address }}
                </div>
                <div class="text-xs text-gray-500" v-if="company.district || company.province">
                    {{ company.district }}{{ company.province ? ', ' + company.province : '' }}{{ company.department ? ' - ' + company.department : '' }}
                </div>
                
                <!--  Contacto -->
                <div class="text-xs text-gray-500" v-if="company.phone || company.email">
                    <span v-if="company.phone">Tel: {{ company.phone }}</span>
                    <span v-if="company.phone && company.email"> | </span>
                    <span v-if="company.email">{{ company.email }}</span>
                </div>
                
                <!-- Tipo y Número de Comprobante -->
                <div class="text-xs font-medium mt-2">
                    {{ selectedJournal?.name || 'Documento' }}
                </div>
                <div class="text-base font-bold">
                    <span v-if="selectedJournal">
                        {{ selectedJournal.code }} <span class="text-gray-400 mx-1">-</span> <span class="text-gray-500">PENDIENTE</span>
                    </span>
                    <span v-else class="text-gray-400">--</span>
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
                    <span class="text-gray-700">{{ (session as any)?.pos_config?.name || '-' }}</span>
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
                    <span class="text-gray-700">{{ client?.name || '—' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="font-semibold text-gray-800">Documento:</span>
                    <span class="text-gray-700">{{ client?.dni || '—' }}</span>
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
                    <span>{{ formatCurrency(parseFloat(String(paymentAmounts[payment.id] || '0'))) }}</span>
                </li>
            </ul>
        </div>

        <!-- 5. PIE DE PÁGINA -->
        <div class="px-6 py-4 border-t text-center text-xs space-y-2">
            <p class="font-semibold">¡Gracias por su compra!</p>
            <p class="text-gray-600">Visite nuestro sitio web</p>
            <p class="text-gray-600">{{ company.email }}</p>
        </div>
    </div>
</template>

<style scoped>
/* Estilos de impresión */
@media print {
    .receipt-printable {
        position: fixed;
        top: 0;
        left: 0;
        width: 80mm;
        margin: 0;
        padding: 0;
        border: none;
    }
    
    /* Configurar el tamaño de página para impresoras térmicas */
    @page {
        margin: 0;
        size: 80mm auto;
    }
}

/* Estilos normales */
.receipt-printable {
    font-family: 'Courier New', monospace;
}
</style>
