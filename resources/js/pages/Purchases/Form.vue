<script setup lang="ts">
import AsyncComboboxWithCreateDialog from '@/components/AsyncComboboxWithCreateDialog.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import ProductForm from '@/pages/Products/Form.vue';
import SupplierForm from '@/pages/Suppliers/Form.vue';
import axios from 'axios';
import { Clock, Plus, Trash2, User } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

type Mode = 'create' | 'edit';

const SupplierFormComponent = SupplierForm;
const ProductFormComponent = ProductForm;

interface Warehouse {
    id: number;
    name: string;
}

interface Tax {
    id: number;
    name: string;
    rate_percent: number;
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

interface Productable {
    id: number;
    product_product_id: number;
    quantity: number;
    price: number;
    tax_id: number | null;
}

interface Purchase {
    id: number;
    status: 'draft' | 'posted' | 'cancelled';
    partner_id: number;
    warehouse_id: number;
    vendor_bill_number: string | null;
    vendor_bill_date: string | null;
    observation: string | null;
    productables: Productable[];
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
    purchaseId?: number | null;
}>();

const emit = defineEmits<{
    (e: 'loaded', purchase: Purchase): void;
    (e: 'saved', purchase: Purchase): void;
}>();

const purchase = ref<Purchase | null>(null);
const activities = ref<Activity[]>([]);

const warehouses = ref<Warehouse[]>([]);
const taxes = ref<Tax[]>([]);

const processing = ref(false);
const errors = ref<ErrorBag>({});

const form = ref({
    partner_id: null as number | null,
    warehouse_id: null as number | null,
    vendor_bill_number: '',
    vendor_bill_date: '',
    observation: '',
    products: [] as ProductLine[],
});

const headers = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

const errorText = (key: string): string | null => {
    const value = errors.value[key];
    if (!value) return null;
    return Array.isArray(value) ? value[0] : String(value);
};

const handleSupplierChange = (value: any) => {
    if (value === null || value === undefined || value === '') {
        form.value.partner_id = null;
        return;
    }
    const parsed = Number(value);
    form.value.partner_id = Number.isFinite(parsed) ? parsed : null;
};

const supplierOptionLabel = (s: any) => {
    const label =
        s?.display_name ||
        s?.business_name ||
        [s?.first_name, s?.last_name].filter(Boolean).join(' ');

    if (s?.document_number) {
        return `${label} (${s.document_number})`;
    }

    return label || 'Proveedor';
};

const createSupplierLabel = (q: string) => {
    const query = q?.trim();
    return query ? `Crear "${query}"` : 'Crear proveedor';
};

const createProductLabel = (q: string) => {
    const query = q?.trim();
    return query ? `Crear "${query}"` : 'Crear producto';
};

const getCreatedProductProductId = (productTemplate: any): number | null => {
    const principal = productTemplate?.product_products?.find(
        (p: any) => p?.is_principal,
    );
    const id = principal?.id ?? null;
    return typeof id === 'number' ? id : null;
};

const productOptionLabel = (p: any) => {
    return (p?.display_name ?? p?.name ?? '').toString();
};

const productSearchParams = computed(() => {
    const params: any = {};
    if (form.value.warehouse_id) {
        params.warehouse_id = form.value.warehouse_id;
    }
    return params;
});

const handleProductSelected = (product: any, line: ProductLine) => {
    line.price = product?.cost_price || 0;
    if (!line.quantity || line.quantity === 0) {
        line.quantity = 1;
    }
};

const handleProductCreated = async (
    createdTemplate: any,
    line: ProductLine,
) => {
    const productProductId = getCreatedProductProductId(createdTemplate);
    if (!productProductId) return;
    try {
        const response = await axios.get(`/api/products/${productProductId}`);
        const product = response.data?.data ?? response.data;
        handleProductSelected(product, line);
    } catch {
        handleProductSelected(createdTemplate, line);
    }
};

const isEditable = computed(() => {
    if (props.mode === 'create') return true;
    return purchase.value?.status === 'draft';
});

const addProductLine = () => {
    const defaultTaxId =
        taxes.value.find((t) => t.rate_percent === 18)?.id ?? null;
    form.value.products.push({
        product_product_id: null,
        quantity: 1,
        price: 0,
        tax_id: defaultTaxId,
    });
};

const removeProductLine = (index: number) => {
    form.value.products.splice(index, 1);
};

const getTaxRate = (taxId: number | null) => {
    if (!taxId) return 0;
    const tax = taxes.value.find((t) => t.id === taxId);
    return tax ? tax.rate_percent : 0;
};

const calculateLineSubtotal = (line: ProductLine) => line.quantity * line.price;

const calculateLineTax = (line: ProductLine) => {
    const subtotal = calculateLineSubtotal(line);
    const taxRate = getTaxRate(line.tax_id);
    return subtotal * (taxRate / 100);
};

const calculateLineTotal = (line: ProductLine) =>
    calculateLineSubtotal(line) + calculateLineTax(line);

const grandSubtotal = computed(() =>
    form.value.products.reduce(
        (sum, line) => sum + calculateLineSubtotal(line),
        0,
    ),
);

const grandTaxTotal = computed(() =>
    form.value.products.reduce((sum, line) => sum + calculateLineTax(line), 0),
);

const grandTotal = computed(() =>
    form.value.products.reduce(
        (sum, line) => sum + calculateLineTotal(line),
        0,
    ),
);

const loadFormOptions = async () => {
    const response = await axios.get('/api/purchases/form-options', {
        headers,
    });
    const data = response.data?.data || {};
    warehouses.value = (data.warehouses || []) as Warehouse[];
    taxes.value = (data.taxes || []) as Tax[];
};

const loadPurchase = async () => {
    if (props.mode !== 'edit' || !props.purchaseId) return;

    const response = await axios.get(`/api/purchases/${props.purchaseId}`, {
        headers,
    });

    purchase.value = response.data?.data as Purchase;
    activities.value = (response.data?.meta?.activities || []) as Activity[];

    if (purchase.value) {
        form.value = {
            partner_id: purchase.value.partner_id,
            warehouse_id: purchase.value.warehouse_id,
            vendor_bill_number: purchase.value.vendor_bill_number || '',
            vendor_bill_date: purchase.value.vendor_bill_date || '',
            observation: purchase.value.observation || '',
            products: (purchase.value.productables || []).map((p) => ({
                product_product_id: p.product_product_id,
                quantity: Number(p.quantity),
                price: Number(p.price),
                tax_id: p.tax_id,
            })),
        };
        emit('loaded', purchase.value);
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

const getActivityDescription = (activity: Activity) => {
    if (
        activity.description &&
        !activity.description.startsWith('updated') &&
        !activity.description.startsWith('created')
    ) {
        return activity.description;
    }

    if (activity.event === 'created') {
        return 'Compra Creada';
    }

    if (activity.event === 'updated' && activity.properties) {
        const attributes = activity.properties.attributes || {};
        const old = activity.properties.old || {};

        if (attributes.status && old.status) {
            if (attributes.status === 'posted') return 'Compra Publicada';
            if (attributes.status === 'cancelled') return 'Compra Cancelada';
            if (attributes.status === 'draft') return 'Revertida a Borrador';
        }

        const changes = [];
        if (attributes.partner_id !== undefined) changes.push('proveedor');
        if (attributes.warehouse_id !== undefined) changes.push('almacén');
        if (attributes.total !== undefined) changes.push('total');
        if (attributes.observation !== undefined) changes.push('observaciones');

        if (changes.length > 0) {
            return `Actualizado: ${changes.join(', ')}`;
        }
    }

    return activity.description || 'Actualización';
};

const submit = async () => {
    processing.value = true;
    errors.value = {};

    try {
        const payload = {
            partner_id: form.value.partner_id,
            warehouse_id: form.value.warehouse_id,
            vendor_bill_number: form.value.vendor_bill_number || null,
            vendor_bill_date: form.value.vendor_bill_date || null,
            observation: form.value.observation || null,
            products: form.value.products.map((p) => ({
                product_product_id: p.product_product_id,
                quantity: p.quantity,
                price: p.price,
                tax_id: p.tax_id,
            })),
        };

        if (props.mode === 'create') {
            const response = await axios.post('/api/purchases', payload, {
                headers,
            });
            const saved = response.data?.data as Purchase;
            purchase.value = saved;
            emit('saved', saved);
            return;
        }

        if (!props.purchaseId) return;

        const response = await axios.put(
            `/api/purchases/${props.purchaseId}`,
            payload,
            {
                headers,
            },
        );
        const saved = response.data?.data as Purchase;
        purchase.value = saved;
        emit('saved', saved);
    } catch (e: any) {
        if (e?.response?.status === 422) {
            errors.value = (e.response.data?.errors || {}) as ErrorBag;
        } else {
            console.error('Error saving purchase:', e);
        }
    } finally {
        processing.value = false;
    }
};

defineExpose({
    submit,
    processing,
    isEditable,
});

onMounted(async () => {
    try {
        await loadFormOptions();
        await loadPurchase();

        if (props.mode === 'create' && form.value.products.length === 0) {
            addProductLine();
        }
    } catch (e) {
        console.error('Error loading purchase form:', e);
    }
});
</script>

<template>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <Alert v-if="props.mode === 'edit' && !isEditable" class="mb-6">
                <AlertTitle>Compra Publicada</AlertTitle>
                <AlertDescription>
                    Esta compra ya está publicada. No se puede editar desde el
                    formulario.
                </AlertDescription>
            </Alert>

            <form @submit.prevent="submit" class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Información General</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="partner_id">Proveedor *</Label>
                            <AsyncComboboxWithCreateDialog
                                :model-value="form.partner_id"
                                :disabled="!isEditable"
                                placeholder="Seleccionar proveedor"
                                search-url="/api/suppliers"
                                get-url-template="/api/suppliers/{id}"
                                :limit="8"
                                :option-label="supplierOptionLabel"
                                @update:model-value="handleSupplierChange"
                                show-create
                                :create-label="createSupplierLabel"
                                create-title="Nuevo Proveedor"
                                create-description="Crea un nuevo proveedor sin salir del formulario"
                                :form-component="SupplierFormComponent"
                            />
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
                                <SelectTrigger id="warehouse_id">
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

                        <div class="space-y-2">
                            <Label for="vendor_bill_number"
                                >Factura del Proveedor</Label
                            >
                            <Input
                                id="vendor_bill_number"
                                v-model="form.vendor_bill_number"
                                placeholder="Ej: F001-192"
                                :disabled="!isEditable"
                            />
                            <p class="text-xs text-muted-foreground">
                                Número de la factura emitida por el proveedor
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="vendor_bill_date"
                                >Fecha de Factura</Label
                            >
                            <Input
                                id="vendor_bill_date"
                                v-model="form.vendor_bill_date"
                                type="date"
                                :disabled="!isEditable"
                            />
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="observation">Observaciones</Label>
                            <Textarea
                                id="observation"
                                v-model="form.observation"
                                placeholder="Observaciones o notas adicionales"
                                rows="2"
                                :disabled="!isEditable"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between"
                    >
                        <CardTitle>Productos</CardTitle>
                        <Button
                            type="button"
                            @click="addProductLine"
                            size="sm"
                            :disabled="!isEditable"
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
                                        <AsyncComboboxWithCreateDialog
                                            v-model="line.product_product_id"
                                            :disabled="!isEditable"
                                            placeholder="Buscar producto..."
                                            search-url="/api/products/search"
                                            get-url-template="/api/products/{id}"
                                            :limit="20"
                                            :extra-params="productSearchParams"
                                            :option-label="productOptionLabel"
                                            show-create
                                            :create-label="createProductLabel"
                                            create-title="Nuevo Producto"
                                            create-description="Crea un nuevo producto sin salir del formulario"
                                            :form-component="
                                                ProductFormComponent
                                            "
                                            :created-id="
                                                getCreatedProductProductId
                                            "
                                            @select="
                                                (p: any) =>
                                                    handleProductSelected(
                                                        p,
                                                        line,
                                                    )
                                            "
                                            @created="
                                                (p: any) =>
                                                    handleProductCreated(
                                                        p,
                                                        line,
                                                    )
                                            "
                                        />
                                    </TableCell>

                                    <TableCell>
                                        <Input
                                            v-model.number="line.quantity"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            :disabled="!isEditable"
                                            class="w-full"
                                        />
                                    </TableCell>

                                    <TableCell>
                                        <Input
                                            v-model.number="line.price"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            :disabled="!isEditable"
                                            class="w-full"
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
                                                <SelectItem :value="null"
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
                                        {{
                                            calculateLineSubtotal(line).toFixed(
                                                2,
                                            )
                                        }}
                                    </TableCell>
                                    <TableCell class="text-right font-mono">
                                        {{ calculateLineTax(line).toFixed(2) }}
                                    </TableCell>
                                    <TableCell
                                        class="text-right font-mono font-medium"
                                    >
                                        {{
                                            calculateLineTotal(line).toFixed(2)
                                        }}
                                    </TableCell>

                                    <TableCell>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            @click="removeProductLine(index)"
                                            :disabled="
                                                !isEditable ||
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
                        <p
                            v-if="errorText('status')"
                            class="mt-2 text-sm text-destructive"
                        >
                            {{ errorText('status') }}
                        </p>
                    </CardContent>
                </Card>
            </form>
        </div>

        <div class="lg:col-span-1">
            <Card class="sticky top-4">
                <CardHeader>
                    <CardTitle>Historial de Cambios</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div
                            v-for="(activity, index) in activities"
                            :key="index"
                            class="flex gap-3 text-sm"
                        >
                            <div class="flex-shrink-0">
                                <Clock class="h-4 w-4 text-muted-foreground" />
                            </div>
                            <div class="flex-1 space-y-1">
                                <p class="font-medium">
                                    {{ getActivityDescription(activity) }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ formatDate(activity.created_at) }}
                                </p>
                                <p
                                    v-if="activity.causer"
                                    class="flex items-center gap-1 text-xs text-muted-foreground"
                                >
                                    <User class="h-3 w-3" />
                                    {{ activity.causer.name }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="activities.length === 0"
                            class="py-4 text-center text-sm text-muted-foreground"
                        >
                            No hay actividades registradas
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
