<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
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
import PosLayout from '@/layouts/PosLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronLeft,
    ChevronRight,
    RefreshCw,
    Search,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{
    session: any;
    paymentMethods: any[];
    errors: Record<string, string>;
}>();

const page = usePage();
const searchQuery = ref('');
const isLoading = ref(false);
const isLoadingDetails = ref(false);
const isProcessing = ref(false);
const searchResults = ref<any[]>([]);
const pagination = ref<any>({
    current_page: 1,
    last_page: 1,
    total: 0,
});
const selectedSale = ref<any>(null);
const refundItems = ref<Record<number, number>>({});
const selectedPaymentMethodId = ref<string>('');
const formErrors = ref<Record<string, string>>({});

const csrfToken = () => {
    const el = document.querySelector('meta[name="csrf-token"]');
    return el?.getAttribute('content') || '';
};

const searchSales = async (page = 1) => {
    isLoading.value = true;
    selectedSale.value = null;

    try {
        const params = new URLSearchParams();
        if (searchQuery.value.trim()) {
            params.set('q', searchQuery.value.trim());
        } else {
            params.set('days', '7');
        }
        params.set('page', page.toString());
        params.set('per_page', '10');

        const response = await fetch(
            `/pos/${props.session.id}/refund/orders?${params.toString()}`,
            {
                headers: {
                    Accept: 'application/json',
                },
            },
        );
        const data = await response.json();
        searchResults.value = data.data;
        pagination.value = data.meta;
    } catch (e) {
        console.error(e);
        searchResults.value = [];
    } finally {
        isLoading.value = false;
    }
};

const selectSale = async (sale: any) => {
    isLoadingDetails.value = true;
    refundItems.value = {};
    selectedPaymentMethodId.value = '';

    try {
        const response = await fetch(
            `/pos/${props.session.id}/refund/sales/${sale.id}`,
            {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            },
        );

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || 'Error al cargar detalles');
        }

        const data = await response.json();
        selectedSale.value = data;
    } catch (e) {
        console.error(e);
        // Show error notification if needed
    } finally {
        isLoadingDetails.value = false;
    }
};

const updateRefundQty = (itemId: number, qty: number, max: number) => {
    if (qty < 0) qty = 0;
    if (qty > max) qty = max;

    if (qty > 0) {
        refundItems.value[itemId] = qty;
    } else {
        delete refundItems.value[itemId];
    }
};

const refundTotal = computed(() => {
    if (!selectedSale.value) return 0;

    let total = 0;
    for (const item of selectedSale.value.items) {
        const qty = refundItems.value[item.product_product_id] || 0;
        if (qty > 0) {
            const price = item.price;
            const taxRate = item.tax_rate || 0;
            const subtotal = qty * price;
            const tax = subtotal * (taxRate / 100);
            total += subtotal + tax;
        }
    }
    return total;
});

const canProcess = computed(() => {
    return (
        Object.keys(refundItems.value).length > 0 &&
        selectedPaymentMethodId.value
    );
});

const processRefund = () => {
    if (!canProcess.value || !selectedSale.value) return;

    isProcessing.value = true;
    formErrors.value = {};

    const returnItems = Object.entries(refundItems.value).map(([id, qty]) => ({
        product_product_id: parseInt(id),
        quantity: qty,
    }));

    router.post(
        `/pos/${props.session.id}/refund/process`,
        {
            origin_sale_id: selectedSale.value.id,
            return_items: returnItems,
            refund_amount: refundTotal.value,
            refund_payment_method_id: parseInt(selectedPaymentMethodId.value),
        },
        {
            onError: (errors) => {
                console.error('Refund errors:', errors);
                formErrors.value = errors;
            },
            onFinish: () => {
                isProcessing.value = false;
            },
        },
    );
};

const goBack = () => {
    router.visit(`/pos/${props.session.id}`);
};

const formatCurrency = (val: number) => {
    return `S/ ${val.toFixed(2)}`;
};

onMounted(() => {
    searchSales(1);
});
</script>

<template>
    <PosLayout title="POS - Reembolsos">
        <template #header-actions>
            <Button variant="outline" size="sm" @click="goBack">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Volver al POS
            </Button>
        </template>

        <div class="flex h-[calc(100vh-80px)] gap-4">
            <!-- Left Panel: Search & List -->
            <div class="flex w-1/3 flex-col gap-4">
                <Card class="space-y-4 p-4">
                    <div class="space-y-2">
                        <Label>Buscar Venta</Label>
                        <div class="flex gap-2">
                            <Input
                                v-model="searchQuery"
                                placeholder="Serie-Correlativo o Cliente..."
                                @keyup.enter="searchSales(1)"
                            />
                            <Button
                                @click="searchSales(1)"
                                :disabled="isLoading"
                            >
                                <Search class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </Card>

                <Card class="flex flex-1 flex-col overflow-hidden">
                    <div
                        class="flex items-center justify-between border-b bg-muted/20 p-4 font-semibold"
                    >
                        <span>Resultados</span>
                        <span
                            v-if="pagination.total"
                            class="text-xs text-muted-foreground"
                            >{{ pagination.total }} encontrados</span
                        >
                    </div>
                    <div class="flex-1 overflow-y-auto p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Documento</TableHead>
                                    <TableHead>Cliente</TableHead>
                                    <TableHead class="text-right"
                                        >Total</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="sale in searchResults"
                                    :key="sale.id"
                                    class="cursor-pointer hover:bg-muted/50"
                                    :class="
                                        selectedSale?.id === sale.id
                                            ? 'bg-muted'
                                            : ''
                                    "
                                    @click="selectSale(sale)"
                                >
                                    <TableCell class="font-medium">
                                        {{ sale.document }}
                                        <div
                                            class="text-[10px] text-muted-foreground"
                                        >
                                            {{
                                                sale.created_at.substring(0, 10)
                                            }}
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-xs">{{
                                        sale.partner_name
                                    }}</TableCell>
                                    <TableCell class="text-right font-medium"
                                        >S/
                                        {{ sale.total.toFixed(2) }}</TableCell
                                    >
                                </TableRow>
                                <TableRow
                                    v-if="
                                        searchResults.length === 0 && !isLoading
                                    "
                                >
                                    <TableCell
                                        colspan="3"
                                        class="py-8 text-center text-muted-foreground"
                                    >
                                        {{
                                            searchQuery
                                                ? 'No se encontraron resultados'
                                                : 'No hay ventas recientes'
                                        }}
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="isLoading">
                                    <TableCell
                                        colspan="3"
                                        class="py-8 text-center text-muted-foreground"
                                    >
                                        Cargando...
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                    <!-- Pagination -->
                    <div
                        class="flex items-center justify-between border-t bg-muted/20 p-2"
                        v-if="pagination.last_page > 1"
                    >
                        <Button
                            variant="ghost"
                            size="sm"
                            :disabled="
                                pagination.current_page <= 1 || isLoading
                            "
                            @click="searchSales(pagination.current_page - 1)"
                        >
                            <ChevronLeft class="h-4 w-4" />
                        </Button>
                        <span class="text-xs text-muted-foreground">
                            {{ pagination.current_page }} /
                            {{ pagination.last_page }}
                        </span>
                        <Button
                            variant="ghost"
                            size="sm"
                            :disabled="
                                pagination.current_page >=
                                    pagination.last_page || isLoading
                            "
                            @click="searchSales(pagination.current_page + 1)"
                        >
                            <ChevronRight class="h-4 w-4" />
                        </Button>
                    </div>
                </Card>
            </div>

            <!-- Right Panel: Refund Details -->
            <div class="flex min-w-0 flex-1 flex-col">
                <Card
                    v-if="!selectedSale"
                    class="flex h-full items-center justify-center text-muted-foreground"
                >
                    <div class="text-center">
                        <RefreshCw class="mx-auto mb-4 h-12 w-12 opacity-50" />
                        <h3 class="text-lg font-medium">
                            Selecciona una venta
                        </h3>
                        <p>
                            Busca y selecciona una venta para ver sus detalles
                        </p>
                    </div>
                </Card>

                <div v-else class="flex h-full flex-col gap-4">
                    <!-- Sale Info -->
                    <Card class="p-4">
                        <Alert
                            v-if="Object.keys(formErrors).length > 0"
                            variant="destructive"
                            class="mb-4"
                        >
                            <AlertCircle class="h-4 w-4" />
                            <AlertTitle>Error</AlertTitle>
                            <AlertDescription>
                                <ul class="list-disc pl-4">
                                    <li
                                        v-for="(error, key) in formErrors"
                                        :key="key"
                                    >
                                        {{ error }}
                                    </li>
                                </ul>
                            </AlertDescription>
                        </Alert>

                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-xl font-bold">
                                    {{ selectedSale.document }}
                                </h3>
                                <p class="text-muted-foreground">
                                    {{
                                        selectedSale.partner?.display_name ||
                                        'Cliente General'
                                    }}
                                </p>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                @click="selectedSale = null"
                            >
                                <X class="h-4 w-4" />
                            </Button>
                        </div>
                    </Card>

                    <!-- Items Table -->
                    <Card class="flex flex-1 flex-col overflow-hidden">
                        <div class="border-b bg-muted/20 p-4 font-semibold">
                            Items Disponibles para Devolución
                        </div>
                        <div class="flex-1 overflow-y-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Producto</TableHead>
                                        <TableHead class="text-right"
                                            >Precio</TableHead
                                        >
                                        <TableHead class="text-center"
                                            >Cant. Comprada</TableHead
                                        >
                                        <TableHead class="text-center"
                                            >Disponible</TableHead
                                        >
                                        <TableHead class="w-[150px] text-center"
                                            >Devolver</TableHead
                                        >
                                        <TableHead class="text-right"
                                            >Subtotal</TableHead
                                        >
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="item in selectedSale.items"
                                        :key="item.product_product_id"
                                    >
                                        <TableCell>
                                            <div class="font-medium">
                                                {{ item.name }}
                                            </div>
                                            <div
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ item.sku }}
                                            </div>
                                        </TableCell>
                                        <TableCell class="text-right">{{
                                            formatCurrency(item.price)
                                        }}</TableCell>
                                        <TableCell class="text-center">{{
                                            item.qty_sold
                                        }}</TableCell>
                                        <TableCell class="text-center">
                                            <Badge variant="secondary">{{
                                                item.qty_available
                                            }}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                type="number"
                                                min="0"
                                                :max="item.qty_available"
                                                class="text-center"
                                                :disabled="
                                                    item.qty_available <= 0
                                                "
                                                :model-value="
                                                    refundItems[
                                                        item.product_product_id
                                                    ] || 0
                                                "
                                                @update:model-value="
                                                    (v) =>
                                                        updateRefundQty(
                                                            item.product_product_id,
                                                            Number(v),
                                                            item.qty_available,
                                                        )
                                                "
                                            />
                                        </TableCell>
                                        <TableCell
                                            class="text-right font-semibold"
                                        >
                                            {{
                                                formatCurrency(
                                                    (refundItems[
                                                        item.product_product_id
                                                    ] || 0) *
                                                        item.price *
                                                        (1 +
                                                            (item.tax_rate ||
                                                                0) /
                                                                100),
                                                )
                                            }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </Card>

                    <!-- Footer Actions -->
                    <Card class="p-4">
                        <div class="flex items-end justify-between gap-6">
                            <div class="w-1/3 space-y-2">
                                <Label>Método de Devolución</Label>
                                <Select v-model="selectedPaymentMethodId">
                                    <SelectTrigger>
                                        <SelectValue
                                            placeholder="Seleccionar método"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="method in props.paymentMethods"
                                            :key="method.id"
                                            :value="method.id.toString()"
                                        >
                                            {{ method.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="flex-1 space-y-1 text-right">
                                <div class="text-sm text-muted-foreground">
                                    Total a Reembolsar
                                </div>
                                <div class="text-3xl font-bold text-primary">
                                    {{ formatCurrency(refundTotal) }}
                                </div>
                            </div>

                            <Button
                                size="lg"
                                :disabled="!canProcess || isProcessing"
                                @click="processRefund"
                                class="min-w-[200px] bg-red-600 text-white hover:bg-red-700"
                            >
                                <RefreshCw class="mr-2 h-4 w-4" />
                                {{
                                    isProcessing
                                        ? 'Procesando...'
                                        : 'Confirmar Reembolso'
                                }}
                            </Button>
                        </div>
                    </Card>
                </div>
            </div>
        </div>
    </PosLayout>
</template>
