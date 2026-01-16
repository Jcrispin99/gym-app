<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import PosLayout from '@/layouts/PosLayout.vue';
import { router } from '@inertiajs/vue3';
import { ArrowLeft, FileText } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface PosSession {
    id: number;
    opened_at: string;
    status: string;
    user?: {
        id: number;
        name: string;
        email: string;
    };
    posConfig?: {
        id: number;
        name: string;
    };
}

interface OrderItem {
    id: number;
    product_product_id: number;
    product_name: string | null;
    sku: string | null;
    quantity: number;
    price: number;
    subtotal: number;
    tax_rate: number;
    tax_amount: number;
    total: number;
}

interface Order {
    id: number;
    serie: string;
    correlative: string;
    date: string | null;
    partner: {
        id: number;
        display_name: string;
        document_type: string;
        document_number: string;
    } | null;
    subtotal: number;
    tax_amount: number;
    total: number;
    status: string;
    payment_status: string;
    items: OrderItem[];
}

interface Props {
    session: PosSession;
    orders: Order[];
    returnTo?: string;
}

const props = defineProps<Props>();

const expandedOrderIds = ref<number[]>([]);

const toggleOrder = (orderId: number) => {
    expandedOrderIds.value = expandedOrderIds.value.includes(orderId)
        ? expandedOrderIds.value.filter((id) => id !== orderId)
        : [...expandedOrderIds.value, orderId];
};

const formatCurrency = (value: number) => {
    return `S/ ${value.toFixed(2)}`;
};

const formatDateTime = (iso: string | null) => {
    if (!iso) return '-';
    return new Date(iso).toLocaleString('es-PE', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const statusLabel = (status: string) => {
    if (status === 'posted') return 'Emitida';
    if (status === 'draft') return 'Borrador';
    if (status === 'cancelled') return 'Anulada';
    return status;
};

const paymentLabel = (status: string) => {
    if (status === 'paid') return 'Pagada';
    if (status === 'partial') return 'Parcial';
    if (status === 'unpaid') return 'Pendiente';
    return status;
};

const headerTitle = computed(() => {
    return `Órdenes · Sesión #${props.session.id}`;
});

const backToDashboard = () => {
    router.visit(props.returnTo || `/pos/${props.session.id}`);
};

const sessionTotal = computed(() => {
    return props.orders.reduce((acc, order) => acc + (order.total || 0), 0);
});
</script>

<template>
    <PosLayout :title="headerTitle">
        <template #header-actions>
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="backToDashboard"
            >
                <ArrowLeft class="mr-2 h-4 w-4" />
                Volver
            </Button>
        </template>

        <div class="mx-auto w-full p-4">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="h-4 w-4" />
                        Órdenes de la sesión actual
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Documento</TableHead>
                                <TableHead>Fecha</TableHead>
                                <TableHead>Cliente</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>Pago</TableHead>
                                <TableHead class="text-right">Total</TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            <TableRow v-if="props.orders.length === 0">
                                <TableCell colspan="6" class="text-center">
                                    No hay órdenes registradas en esta sesión
                                </TableCell>
                            </TableRow>

                            <template
                                v-for="order in props.orders"
                                :key="order.id"
                            >
                                <TableRow
                                    class="cursor-pointer hover:bg-accent/50"
                                    @click="toggleOrder(order.id)"
                                >
                                    <TableCell>
                                        <div class="font-medium">
                                            {{ order.serie }}-{{
                                                order.correlative
                                            }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            #{{ order.id }} ·
                                            {{ order.items.length }} item(s)
                                        </div>
                                    </TableCell>
                                    <TableCell>{{
                                        formatDateTime(order.date)
                                    }}</TableCell>
                                    <TableCell>
                                        <div class="font-medium">
                                            {{
                                                order.partner?.display_name ||
                                                '—'
                                            }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                order.partner
                                                    ? `${order.partner.document_type} ${order.partner.document_number}`
                                                    : ''
                                            }}
                                        </div>
                                    </TableCell>
                                    <TableCell>{{
                                        statusLabel(order.status)
                                    }}</TableCell>
                                    <TableCell>{{
                                        paymentLabel(order.payment_status)
                                    }}</TableCell>
                                    <TableCell class="text-right">
                                        {{ formatCurrency(order.total) }}
                                    </TableCell>
                                </TableRow>

                                <TableRow
                                    v-if="expandedOrderIds.includes(order.id)"
                                >
                                    <TableCell colspan="6" class="bg-muted/30">
                                        <div class="space-y-2">
                                            <div class="text-sm font-medium">
                                                Productos (productables)
                                            </div>
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead
                                                            >Producto</TableHead
                                                        >
                                                        <TableHead
                                                            class="text-right"
                                                            >Cant.</TableHead
                                                        >
                                                        <TableHead
                                                            class="text-right"
                                                            >Precio</TableHead
                                                        >
                                                        <TableHead
                                                            class="text-right"
                                                            >Subt.</TableHead
                                                        >
                                                        <TableHead
                                                            class="text-right"
                                                            >IGV</TableHead
                                                        >
                                                        <TableHead
                                                            class="text-right"
                                                            >Total</TableHead
                                                        >
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    <TableRow
                                                        v-if="
                                                            order.items
                                                                .length === 0
                                                        "
                                                    >
                                                        <TableCell
                                                            colspan="6"
                                                            class="text-center"
                                                        >
                                                            Sin items
                                                        </TableCell>
                                                    </TableRow>
                                                    <TableRow
                                                        v-for="item in order.items"
                                                        :key="item.id"
                                                    >
                                                        <TableCell>
                                                            <div
                                                                class="font-medium"
                                                            >
                                                                {{
                                                                    item.product_name ||
                                                                    'Producto'
                                                                }}
                                                            </div>
                                                            <div
                                                                class="text-xs text-muted-foreground"
                                                            >
                                                                ID:
                                                                {{
                                                                    item.product_product_id
                                                                }}
                                                                <span
                                                                    v-if="
                                                                        item.sku
                                                                    "
                                                                >
                                                                    · SKU:
                                                                    {{
                                                                        item.sku
                                                                    }}
                                                                </span>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell
                                                            class="text-right"
                                                        >
                                                            {{ item.quantity }}
                                                        </TableCell>
                                                        <TableCell
                                                            class="text-right"
                                                        >
                                                            {{
                                                                formatCurrency(
                                                                    item.price,
                                                                )
                                                            }}
                                                        </TableCell>
                                                        <TableCell
                                                            class="text-right"
                                                        >
                                                            {{
                                                                formatCurrency(
                                                                    item.subtotal,
                                                                )
                                                            }}
                                                        </TableCell>
                                                        <TableCell
                                                            class="text-right"
                                                        >
                                                            {{
                                                                formatCurrency(
                                                                    item.tax_amount,
                                                                )
                                                            }}
                                                        </TableCell>
                                                        <TableCell
                                                            class="text-right"
                                                        >
                                                            {{
                                                                formatCurrency(
                                                                    item.total,
                                                                )
                                                            }}
                                                        </TableCell>
                                                    </TableRow>
                                                </TableBody>
                                            </Table>

                                            <div
                                                class="flex justify-end gap-6 text-sm"
                                            >
                                                <div
                                                    class="text-muted-foreground"
                                                >
                                                    Subtotal:
                                                    <span
                                                        class="font-medium text-foreground"
                                                    >
                                                        {{
                                                            formatCurrency(
                                                                order.subtotal,
                                                            )
                                                        }}
                                                    </span>
                                                </div>
                                                <div
                                                    class="text-muted-foreground"
                                                >
                                                    IGV:
                                                    <span
                                                        class="font-medium text-foreground"
                                                    >
                                                        {{
                                                            formatCurrency(
                                                                order.tax_amount,
                                                            )
                                                        }}
                                                    </span>
                                                </div>
                                                <div
                                                    class="text-muted-foreground"
                                                >
                                                    Total:
                                                    <span
                                                        class="font-semibold text-foreground"
                                                    >
                                                        {{
                                                            formatCurrency(
                                                                order.total,
                                                            )
                                                        }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </template>

                            <TableRow
                                v-if="props.orders.length > 0"
                                class="border-t"
                            >
                                <TableCell />
                                <TableCell />
                                <TableCell />
                                <TableCell />
                                <TableCell />
                                <TableCell class="text-right font-semibold">
                                    {{ formatCurrency(sessionTotal) }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </PosLayout>
</template>
