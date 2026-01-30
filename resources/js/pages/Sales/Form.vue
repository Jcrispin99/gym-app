<script setup lang="ts">
import ProductCombobox from '@/components/ProductCombobox.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Clock,
    FileText,
    Plus,
    Trash2,
    User as UserIcon,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

type Mode = 'create' | 'edit';

interface Partner {
    id: number;
    display_name: string;
}

interface Warehouse {
    id: number;
    name: string;
}

interface Tax {
    id: number;
    name: string;
    rate_percent: number;
    is_default?: boolean;
}

interface Activity {
    description: string;
    event: string;
    properties: any;
    created_at: string;
    causer: {
        name: string;
        email: string;
    } | null;
}

interface RelatedSaleLink {
    id: number;
    document: string;
    status: 'draft' | 'posted' | 'cancelled';
    doc_type: string;
    journal_code?: string | null;
    partner_name?: string | null;
}

interface SaleLine {
    product_product_id: number;
    quantity: number;
    price: number;
    tax_id: number | null;
    tax_rate?: number | null;
}

interface Sale {
    id: number;
    serie: string;
    correlative: string;
    status: 'draft' | 'posted' | 'cancelled';
    payment_status: 'unpaid' | 'partial' | 'paid';
    partner_id: number | null;
    warehouse_id: number;
    notes: string | null;
    products: SaleLine[];
    sunat_status?: string | null;
    sunat_response?: { accepted?: boolean; error?: string | null } | null;
    original_sale_id?: number | null;
    journal?: {
        document_type_code?: string | null;
        is_fiscal?: boolean;
    } | null;
}

interface ProductLine {
    product_product_id: number | null;
    quantity: number;
    price: number;
    tax_id: number | null;
}

type ErrorBag = Record<string, string[]>;

const props = defineProps<{
    mode: Mode;
    saleId?: number | null;
}>();

const emit = defineEmits<{
    (e: 'loaded', sale: Sale): void;
    (e: 'saved', sale: Sale): void;
}>();

const headers = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

const customers = ref<Partner[]>([]);
const warehouses = ref<Warehouse[]>([]);
const taxes = ref<Tax[]>([]);

const sale = ref<Sale | null>(null);
const activities = ref<Activity[]>([]);
const originSale = ref<RelatedSaleLink | null>(null);
const creditNotes = ref<RelatedSaleLink[]>([]);

const processing = ref(false);
const errors = ref<ErrorBag>({});

const defaultTaxId = computed<number | undefined>(() => {
    const candidate =
        taxes.value.find((t) => t.is_default)?.id ||
        taxes.value.find((t) => t.rate_percent === 18)?.id;
    return candidate || undefined;
});

const form = ref({
    partner_id: undefined as number | undefined,
    warehouse_id: undefined as number | undefined,
    notes: '',
    products: [] as ProductLine[],
});

const errorText = (key: string): string | null => {
    const value = errors.value[key];
    if (!value) return null;
    return Array.isArray(value) ? value[0] : String(value);
};

const documentNumber = computed(() => {
    if (!sale.value) return '';
    return `${sale.value.serie}-${sale.value.correlative}`;
});

const isEditable = computed(() => {
    if (props.mode === 'create') return true;
    return sale.value?.status === 'draft';
});

const submitDisabled = computed(() => {
    if (processing.value) return true;
    if (isEditable.value) return form.value.products.length === 0;
    return false;
});

const canSendSunat = computed(() => {
    if (!sale.value) return false;
    if (sale.value.journal?.is_fiscal === false) return false;
    if (sale.value.status !== 'posted') return false;
    if (sale.value.sunat_response?.accepted === true) return false;
    return sale.value.sunat_status !== 'accepted';
});

const canCreateCreditNote = computed(() => {
    if (!sale.value) return false;
    if (sale.value.status !== 'posted') return false;
    if (sale.value.original_sale_id) return false;
    const docType = sale.value.journal?.document_type_code;
    return docType === '01' || docType === '03';
});

const addProductLine = () => {
    form.value.products.push({
        product_product_id: null,
        quantity: 1,
        price: 0,
        tax_id: defaultTaxId.value ?? 0,
    });
};

const removeProductLine = (index: number) => {
    form.value.products.splice(index, 1);
};

const resolveTaxRate = (taxId?: number | null) => {
    if (!taxId || taxId === 0) return 0;
    const tax = taxes.value.find((t) => t.id === taxId);
    return tax ? tax.rate_percent : 0;
};

const lineSubtotal = (line: ProductLine) => line.quantity * line.price;
const lineTax = (line: ProductLine) => {
    const subtotal = lineSubtotal(line);
    const rate = resolveTaxRate(line.tax_id);
    return subtotal * (rate / 100);
};
const lineTotal = (line: ProductLine) => lineSubtotal(line) + lineTax(line);

const grandSubtotal = computed(() => {
    return form.value.products.reduce(
        (sum, line) => sum + lineSubtotal(line),
        0,
    );
});
const grandTaxTotal = computed(() => {
    return form.value.products.reduce((sum, line) => sum + lineTax(line), 0);
});
const grandTotal = computed(() => {
    return form.value.products.reduce((sum, line) => sum + lineTotal(line), 0);
});

const loadFormOptions = async () => {
    const response = await axios.get('/api/sales/form-options', { headers });
    customers.value = (response.data?.data?.customers || []) as Partner[];
    warehouses.value = (response.data?.data?.warehouses || []) as Warehouse[];
    taxes.value = (response.data?.data?.taxes || []) as Tax[];
};

const loadSale = async () => {
    if (props.mode !== 'edit' || !props.saleId) return;
    const response = await axios.get(`/api/sales/${props.saleId}`, { headers });
    sale.value = response.data?.data as Sale;
    activities.value = (response.data?.meta?.activities || []) as Activity[];
    originSale.value = (response.data?.meta?.originSale ||
        null) as RelatedSaleLink | null;
    creditNotes.value = (response.data?.meta?.creditNotes ||
        []) as RelatedSaleLink[];

    if (sale.value) {
        form.value.partner_id = sale.value.partner_id ?? undefined;
        form.value.warehouse_id = sale.value.warehouse_id ?? undefined;
        form.value.notes = sale.value.notes || '';
        form.value.products = (sale.value.products || []).map((p) => ({
            product_product_id: p.product_product_id,
            quantity: Number(p.quantity),
            price: Number(p.price),
            tax_id: p.tax_id ? Number(p.tax_id) : 0,
        }));
        emit('loaded', sale.value);
    }
};

const normalizePayload = () => {
    const partnerId =
        form.value.partner_id && form.value.partner_id > 0
            ? form.value.partner_id
            : null;
    const warehouseId = form.value.warehouse_id;

    const products = form.value.products.map((line) => ({
        product_product_id: line.product_product_id,
        quantity: Number(line.quantity),
        price: Number(line.price),
        tax_id: line.tax_id && line.tax_id > 0 ? line.tax_id : null,
    }));

    return {
        partner_id: partnerId,
        warehouse_id: warehouseId,
        notes: form.value.notes || null,
        products,
    };
};

const submit = async () => {
    processing.value = true;
    errors.value = {};

    try {
        if (props.mode === 'create') {
            const payload = normalizePayload();
            const response = await axios.post('/api/sales', payload, {
                headers,
            });
            const saved = response.data?.data as Sale;
            sale.value = saved;
            emit('saved', saved);
            return;
        }

        if (!props.saleId) return;

        if (sale.value && sale.value.status !== 'draft') {
            const response = await axios.put(
                `/api/sales/${props.saleId}`,
                { notes: form.value.notes || null },
                { headers },
            );
            const saved = response.data?.data as Sale;
            sale.value = saved;
            emit('saved', saved);
            return;
        }

        const payload = normalizePayload();
        const response = await axios.put(
            `/api/sales/${props.saleId}`,
            payload,
            { headers },
        );
        const saved = response.data?.data as Sale;
        sale.value = saved;
        emit('saved', saved);
    } catch (e: any) {
        if (e?.response?.status === 422) {
            errors.value = (e.response.data?.errors || {}) as ErrorBag;
        } else {
            console.error('Error saving sale:', e);
        }
    } finally {
        processing.value = false;
    }
};

const postSale = async () => {
    if (!props.saleId) return;
    if (
        !confirm(
            '¿Estás seguro de publicar este documento? Se reducirá el inventario si aplica.',
        )
    )
        return;

    processing.value = true;
    try {
        await axios.post(`/api/sales/${props.saleId}/post`, {}, { headers });
        await loadSale();
    } catch (e: any) {
        if (e?.response?.status === 422) {
            errors.value = (e.response.data?.errors || {}) as ErrorBag;
        } else {
            console.error('Error posting sale:', e);
        }
    } finally {
        processing.value = false;
    }
};

const cancelSale = async () => {
    if (!props.saleId) return;
    if (
        !confirm(
            '¿Estás seguro de cancelar esta venta? Se devolverá el stock al inventario.',
        )
    )
        return;

    processing.value = true;
    try {
        await axios.post(`/api/sales/${props.saleId}/cancel`, {}, { headers });
        await loadSale();
    } catch (e: any) {
        if (e?.response?.status === 422) {
            errors.value = (e.response.data?.errors || {}) as ErrorBag;
        } else {
            console.error('Error cancelling sale:', e);
        }
    } finally {
        processing.value = false;
    }
};

const retrySunat = async () => {
    if (!props.saleId) return;

    processing.value = true;
    try {
        await axios.post(
            `/api/sales/${props.saleId}/sunat/retry`,
            {},
            { headers },
        );
        await loadSale();
    } catch (e) {
        console.error('Error retrying sunat:', e);
    } finally {
        processing.value = false;
    }
};

const createCreditNote = async () => {
    if (!props.saleId) return;

    processing.value = true;
    try {
        const response = await axios.post(
            `/api/sales/${props.saleId}/credit-note`,
            {},
            { headers },
        );
        const created = response.data?.data as Sale;
        router.visit(`/sales/${created.id}/edit`);
    } catch (e: any) {
        if (e?.response?.status === 422) {
            errors.value = (e.response.data?.errors || {}) as ErrorBag;
        } else {
            console.error('Error creating credit note:', e);
        }
    } finally {
        processing.value = false;
    }
};

const deleteSale = async () => {
    if (!props.saleId) return;
    if (!confirm('¿Eliminar este borrador? Esta acción no se puede deshacer.'))
        return;

    processing.value = true;
    try {
        await axios.delete(`/api/sales/${props.saleId}`, { headers });
        router.visit('/sales');
    } catch (e: any) {
        if (e?.response?.status === 422) {
            errors.value = (e.response.data?.errors || {}) as ErrorBag;
        } else {
            console.error('Error deleting sale:', e);
        }
    } finally {
        processing.value = false;
    }
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleString('es-PE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

onMounted(async () => {
    try {
        await loadFormOptions();

        if (props.mode === 'create' && form.value.products.length === 0) {
            addProductLine();
        }

        await loadSale();
    } catch (e) {
        console.error('Error loading sale form:', e);
    }
});

defineExpose({
    submit,
    processing,
    isEditable,
    submitDisabled,
    sale,
    canSendSunat,
    canCreateCreditNote,
    postSale,
    cancelSale,
    retrySunat,
    createCreditNote,
    deleteSale,
});
</script>

<template>
    <div class="space-y-6">
        <Alert v-if="props.mode === 'edit' && sale && sale.status !== 'draft'">
            <AlertTitle>Venta Publicada</AlertTitle>
            <AlertDescription>
                Esta venta ya está publicada. Solo puedes editar las notas.
            </AlertDescription>
        </Alert>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <form @submit.prevent="submit" class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Información General</CardTitle>
                            <CardDescription
                                v-if="props.mode === 'edit' && sale"
                            >
                                <div class="flex items-center gap-2">
                                    <FileText class="h-4 w-4" />
                                    <span class="font-medium">{{
                                        documentNumber
                                    }}</span>
                                </div>
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="partner_id">Cliente</Label>
                                <Select
                                    v-model="form.partner_id"
                                    :disabled="!isEditable"
                                >
                                    <SelectTrigger id="partner_id">
                                        <SelectValue
                                            placeholder="Seleccionar cliente (Opcional)"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="0"
                                            >Cliente General</SelectItem
                                        >
                                        <SelectItem
                                            v-for="customer in customers"
                                            :key="customer.id"
                                            :value="customer.id"
                                        >
                                            {{ customer.display_name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p
                                    v-if="errorText('partner_id')"
                                    class="text-sm text-destructive"
                                >
                                    {{ errorText('partner_id') }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="warehouse_id">Almacén *</Label>
                                <Select
                                    v-model="form.warehouse_id"
                                    :disabled="!isEditable"
                                >
                                    <SelectTrigger
                                        id="warehouse_id"
                                        :class="{
                                            'border-red-500':
                                                errorText('warehouse_id'),
                                        }"
                                    >
                                        <SelectValue
                                            placeholder="Seleccionar almacén"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="warehouse in warehouses"
                                            :key="warehouse.id"
                                            :value="warehouse.id"
                                        >
                                            {{ warehouse.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p
                                    v-if="errorText('warehouse_id')"
                                    class="text-sm text-destructive"
                                >
                                    {{ errorText('warehouse_id') }}
                                </p>
                            </div>

                            <div class="space-y-2 md:col-span-2">
                                <Label for="notes">Notas / Observaciones</Label>
                                <Textarea
                                    id="notes"
                                    v-model="form.notes"
                                    placeholder="Observaciones o notas adicionales"
                                    rows="2"
                                />
                                <p
                                    v-if="errorText('notes')"
                                    class="text-sm text-destructive"
                                >
                                    {{ errorText('notes') }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader
                            class="flex flex-row items-center justify-between"
                        >
                            <CardTitle>Productos</CardTitle>
                            <Button
                                v-if="isEditable"
                                type="button"
                                @click="addProductLine"
                                size="sm"
                            >
                                <Plus class="mr-2 h-4 w-4" />
                                Agregar Producto
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Producto</TableHead>
                                        <TableHead class="w-[120px]"
                                            >Cantidad</TableHead
                                        >
                                        <TableHead class="w-[150px]"
                                            >Precio Unit.</TableHead
                                        >
                                        <TableHead class="w-[150px]"
                                            >Impuesto</TableHead
                                        >
                                        <TableHead class="w-[120px] text-right"
                                            >Subtotal</TableHead
                                        >
                                        <TableHead class="w-[100px] text-right"
                                            >IGV</TableHead
                                        >
                                        <TableHead class="w-[120px] text-right"
                                            >Total</TableHead
                                        >
                                        <TableHead class="w-[50px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="(line, index) in form.products"
                                        :key="index"
                                    >
                                        <TableCell>
                                            <ProductCombobox
                                                v-model="
                                                    line.product_product_id
                                                "
                                                :warehouse-id="
                                                    form.warehouse_id
                                                "
                                                placeholder="Buscar producto..."
                                                :disabled="!isEditable"
                                                @select="
                                                    (product) => {
                                                        line.price =
                                                            product.price || 0;
                                                        if (
                                                            !line.quantity ||
                                                            line.quantity === 0
                                                        ) {
                                                            line.quantity = 1;
                                                        }
                                                    }
                                                "
                                            />
                                        </TableCell>

                                        <TableCell>
                                            <Input
                                                v-model.number="line.quantity"
                                                type="number"
                                                min="0.01"
                                                step="0.01"
                                                class="w-full"
                                                :disabled="!isEditable"
                                            />
                                        </TableCell>

                                        <TableCell>
                                            <Input
                                                v-model.number="line.price"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="w-full"
                                                :disabled="!isEditable"
                                            />
                                        </TableCell>

                                        <TableCell>
                                            <Select
                                                v-model="line.tax_id"
                                                :disabled="!isEditable"
                                            >
                                                <SelectTrigger>
                                                    <SelectValue
                                                        placeholder="Sin IGV"
                                                    />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem :value="0"
                                                        >Sin IGV</SelectItem
                                                    >
                                                    <SelectItem
                                                        v-for="tax in taxes"
                                                        :key="tax.id"
                                                        :value="tax.id"
                                                    >
                                                        {{ tax.name }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </TableCell>

                                        <TableCell class="text-right font-mono">
                                            {{ lineSubtotal(line).toFixed(2) }}
                                        </TableCell>

                                        <TableCell class="text-right font-mono">
                                            {{ lineTax(line).toFixed(2) }}
                                        </TableCell>

                                        <TableCell
                                            class="text-right font-mono font-medium"
                                        >
                                            {{ lineTotal(line).toFixed(2) }}
                                        </TableCell>

                                        <TableCell>
                                            <Button
                                                v-if="isEditable"
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                @click="
                                                    removeProductLine(index)
                                                "
                                                :disabled="
                                                    form.products.length === 1
                                                "
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>

                                    <TableRow class="bg-muted/50">
                                        <TableCell
                                            colspan="4"
                                            class="text-right font-medium"
                                        >
                                            Totales:
                                        </TableCell>
                                        <TableCell
                                            class="text-right font-mono font-bold"
                                        >
                                            S/ {{ grandSubtotal.toFixed(2) }}
                                        </TableCell>
                                        <TableCell
                                            class="text-right font-mono font-bold"
                                        >
                                            S/ {{ grandTaxTotal.toFixed(2) }}
                                        </TableCell>
                                        <TableCell
                                            class="text-right font-mono text-lg font-bold"
                                        >
                                            S/ {{ grandTotal.toFixed(2) }}
                                        </TableCell>
                                        <TableCell></TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>

                            <p
                                v-if="errorText('products')"
                                class="mt-2 text-sm text-destructive"
                            >
                                {{ errorText('products') }}
                            </p>
                        </CardContent>
                    </Card>
                </form>
            </div>

            <div class="lg:col-span-1">
                <div class="space-y-4">
                    <Card v-if="originSale">
                        <CardHeader>
                            <CardTitle>Documento Origen</CardTitle>
                        </CardHeader>
                        <CardContent class="text-sm">
                            <div class="space-y-1">
                                <p class="font-medium">
                                    {{ originSale.document }}
                                </p>
                                <p class="text-muted-foreground">
                                    {{ originSale.partner_name || '-' }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card v-if="creditNotes.length > 0">
                        <CardHeader>
                            <CardTitle>Notas de Crédito</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm">
                            <button
                                v-for="note in creditNotes"
                                :key="note.id"
                                class="w-full text-left text-muted-foreground hover:text-foreground"
                                type="button"
                                @click="router.visit(`/sales/${note.id}/edit`)"
                            >
                                {{ note.document }} ({{ note.status }})
                            </button>
                        </CardContent>
                    </Card>

                    <Card class="sticky top-4">
                        <CardHeader>
                            <CardTitle>Historial</CardTitle>
                            <CardDescription v-if="props.mode === 'edit'">
                                Últimas actividades
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div
                                    v-for="(activity, index) in activities"
                                    :key="index"
                                    class="flex gap-3 text-sm"
                                >
                                    <div class="flex-shrink-0">
                                        <Clock
                                            class="h-4 w-4 text-muted-foreground"
                                        />
                                    </div>
                                    <div class="flex-1 space-y-1">
                                        <p class="font-medium">
                                            {{ activity.description }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                formatDate(activity.created_at)
                                            }}
                                        </p>
                                        <p
                                            v-if="activity.causer"
                                            class="flex items-center gap-1 text-xs text-muted-foreground"
                                        >
                                            <UserIcon class="h-3 w-3" />
                                            {{ activity.causer.name }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    v-if="activities.length === 0"
                                    class="py-4 text-center text-sm text-muted-foreground"
                                >
                                    Sin actividad
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </div>
</template>
