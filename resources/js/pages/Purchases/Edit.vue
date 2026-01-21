<script setup lang="ts">
import FormPageHeader from '@/components/FormPageHeader.vue';
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
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Clock, Plus, Save, Trash2, User } from 'lucide-vue-next';
import { computed } from 'vue';

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

interface Props {
    purchase: Purchase;
    activities: Activity[];
    suppliers: Partner[];
    warehouses: Warehouse[];
    taxes: Tax[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Compras', href: '/purchases' },
    { title: 'Editar', href: `/purchases/${props.purchase.id}/edit` },
];

// Pre-llenar formulario con datos existentes
const form = useForm({
    partner_id: props.purchase.partner_id,
    warehouse_id: props.purchase.warehouse_id,
    vendor_bill_number: props.purchase.vendor_bill_number || '',
    vendor_bill_date: props.purchase.vendor_bill_date || '',
    observation: props.purchase.observation || '',
    products: props.purchase.productables.map((p) => ({
        product_product_id: p.product_product_id,
        quantity: p.quantity,
        price: p.price,
        tax_id: p.tax_id,
    })) as ProductLine[],
});

console.log('📋 Edit form initialized with products:', form.products);
console.log('🏪 Warehouse ID:', form.warehouse_id);
console.log('📦 Purchase status:', props.purchase.status);

const addProductLine = () => {
    form.products.push({
        product_product_id: null,
        quantity: 1,
        price: 0,
        tax_id: props.taxes.find((t) => t.rate_percent === 18)?.id || null,
    });
};

const removeProductLine = (index: number) => {
    form.products.splice(index, 1);
};

const getTaxRate = (taxId: number | null) => {
    if (!taxId) return 0;
    const tax = props.taxes.find((t) => t.id === taxId);
    return tax ? tax.rate_percent : 0;
};

const calculateLineSubtotal = (line: ProductLine) => {
    return line.quantity * line.price;
};

const calculateLineTax = (line: ProductLine) => {
    const subtotal = calculateLineSubtotal(line);
    const taxRate = getTaxRate(line.tax_id);
    return subtotal * (taxRate / 100);
};

const calculateLineTotal = (line: ProductLine) => {
    return calculateLineSubtotal(line) + calculateLineTax(line);
};

const grandTotal = computed(() => {
    return form.products.reduce(
        (sum, line) => sum + calculateLineTotal(line),
        0,
    );
});

const grandSubtotal = computed(() => {
    return form.products.reduce(
        (sum, line) => sum + calculateLineSubtotal(line),
        0,
    );
});

const grandTaxTotal = computed(() => {
    return form.products.reduce((sum, line) => sum + calculateLineTax(line), 0);
});

const submit = () => {
    form.put(`/purchases/${props.purchase.id}`);
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
    // Si ya tiene una descripción personalizada, usarla
    if (
        activity.description &&
        !activity.description.startsWith('updated') &&
        !activity.description.startsWith('created')
    ) {
        return activity.description;
    }

    // Para eventos de creación
    if (activity.event === 'created') {
        return 'Compra Creada';
    }

    // Para eventos de actualización, interpretar cambios
    if (activity.event === 'updated' && activity.properties) {
        const attributes = activity.properties.attributes || {};
        const old = activity.properties.old || {};

        // Detectar cambio de status
        if (attributes.status && old.status) {
            if (attributes.status === 'posted') return 'Compra Publicada';
            if (attributes.status === 'cancelled') return 'Compra Cancelada';
            if (attributes.status === 'draft') return 'Revertida a Borrador';
        }

        // Otros cambios
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

// Determinar si la compra es editable (solo drafts permiten editar productos)
const isEditable = computed(() => {
    const editable = props.purchase.status === 'draft';
    return editable;
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Editar Compra #${purchase.id}`" />

        <div class="container mx-auto max-w-6xl p-4">
            <FormPageHeader
                title="Editar Compra"
                :description="`Compra #${purchase.id}`"
                back-href="/purchases"
            >
                <template #actions>
                    <Button
                        @click="submit"
                        :disabled="
                            form.processing || form.products.length === 0
                        "
                    >
                        <Save class="mr-2 h-4 w-4" />
                        {{ form.processing ? 'Guardando...' : 'Actualizar' }}
                    </Button>
                </template>
            </FormPageHeader>

            <!-- Alert para compras publicadas -->
            <Alert v-if="!isEditable" class="mb-6">
                <AlertTitle>Compra Publicada</AlertTitle>
                <AlertDescription>
                    Esta compra ya está publicada. Solo puedes editar las
                    observaciones. Para modificar productos, primero debes
                    revertir el estado desde el índice.
                </AlertDescription>
            </Alert>

            <!-- Grid Layout: Form (2/3) + Sidebar (1/3) -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Main Content (Left - 2/3) -->
                <div class="lg:col-span-2">
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Información General -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Información General</CardTitle>
                            </CardHeader>
                            <CardContent class="grid gap-4 md:grid-cols-2">
                                <!-- Proveedor -->
                                <div class="space-y-2">
                                    <Label for="partner_id">Proveedor *</Label>
                                    <Select
                                        v-model="form.partner_id"
                                        :disabled="!isEditable"
                                    >
                                        <SelectTrigger id="partner_id">
                                            <SelectValue
                                                placeholder="Seleccionar proveedor"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="supplier in suppliers"
                                                :key="supplier.id"
                                                :value="supplier.id"
                                            >
                                                {{ supplier.display_name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p
                                        v-if="form.errors.partner_id"
                                        class="text-sm text-destructive"
                                    >
                                        {{ form.errors.partner_id }}
                                    </p>
                                </div>

                                <!-- Almacén -->
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
                                        v-if="form.errors.warehouse_id"
                                        class="text-sm text-destructive"
                                    >
                                        {{ form.errors.warehouse_id }}
                                    </p>
                                </div>

                                <!-- Factura del Proveedor -->
                                <div class="space-y-2">
                                    <Label for="vendor_bill_number"
                                        >Factura del Proveedor</Label
                                    >
                                    <Input
                                        id="vendor_bill_number"
                                        v-model="form.vendor_bill_number"
                                        placeholder="Ej: F001-192"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Número de la factura emitida por el
                                        proveedor
                                    </p>
                                </div>

                                <!-- Fecha de Factura -->
                                <div class="space-y-2">
                                    <Label for="vendor_bill_date"
                                        >Fecha de Factura</Label
                                    >
                                    <Input
                                        id="vendor_bill_date"
                                        v-model="form.vendor_bill_date"
                                        type="date"
                                    />
                                </div>

                                <!-- Notas -->
                                <div class="space-y-2 md:col-span-2">
                                    <Label for="observation"
                                        >Observaciones</Label
                                    >
                                    <Textarea
                                        id="observation"
                                        v-model="form.observation"
                                        placeholder="Observaciones o notas adicionales"
                                        rows="2"
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Productos -->
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
                                            <TableHead
                                                class="w-[120px] text-right"
                                                >Subtotal</TableHead
                                            >
                                            <TableHead
                                                class="w-[100px] text-right"
                                                >IGV</TableHead
                                            >
                                            <TableHead
                                                class="w-[120px] text-right"
                                                >Total</TableHead
                                            >
                                            <TableHead
                                                class="w-[50px]"
                                            ></TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="(
                                                line, index
                                            ) in form.products"
                                            :key="index"
                                        >
                                            <!-- Producto -->
                                            <TableCell>
                                                <ProductCombobox
                                                    v-model="
                                                        line.product_product_id
                                                    "
                                                    :warehouse-id="
                                                        form.warehouse_id
                                                    "
                                                    :disabled="!isEditable"
                                                    placeholder="Buscar producto..."
                                                    @select="
                                                        (product) => {
                                                            // Auto-llenar con el ÚLTIMO PRECIO DE COMPRA
                                                            line.price =
                                                                product.cost_price ||
                                                                0;
                                                            if (
                                                                !line.quantity ||
                                                                line.quantity ===
                                                                    0
                                                            ) {
                                                                line.quantity = 1;
                                                            }
                                                        }
                                                    "
                                                />
                                            </TableCell>

                                            <!-- Cantidad -->
                                            <TableCell>
                                                <Input
                                                    v-model.number="
                                                        line.quantity
                                                    "
                                                    type="number"
                                                    min="0.01"
                                                    step="0.01"
                                                    :disabled="!isEditable"
                                                    class="w-full"
                                                />
                                            </TableCell>

                                            <!-- Precio -->
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

                                            <!-- Impuesto -->
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
                                                        <SelectItem
                                                            :value="null"
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

                                            <!-- Subtotal -->
                                            <TableCell
                                                class="text-right font-mono"
                                            >
                                                {{
                                                    calculateLineSubtotal(
                                                        line,
                                                    ).toFixed(2)
                                                }}
                                            </TableCell>

                                            <!-- IGV -->
                                            <TableCell
                                                class="text-right font-mono"
                                            >
                                                {{
                                                    calculateLineTax(
                                                        line,
                                                    ).toFixed(2)
                                                }}
                                            </TableCell>

                                            <!-- Total -->
                                            <TableCell
                                                class="text-right font-mono font-medium"
                                            >
                                                {{
                                                    calculateLineTotal(
                                                        line,
                                                    ).toFixed(2)
                                                }}
                                            </TableCell>

                                            <!-- Acciones -->
                                            <TableCell>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    @click="
                                                        removeProductLine(index)
                                                    "
                                                    :disabled="
                                                        !isEditable ||
                                                        form.products.length ===
                                                            1
                                                    "
                                                >
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </TableCell>
                                        </TableRow>

                                        <!-- Totales -->
                                        <TableRow class="bg-muted/50">
                                            <TableCell
                                                colspan="4"
                                                class="text-right font-medium"
                                                >Totales:</TableCell
                                            >
                                            <TableCell
                                                class="text-right font-mono font-bold"
                                            >
                                                S/
                                                {{ grandSubtotal.toFixed(2) }}
                                            </TableCell>
                                            <TableCell
                                                class="text-right font-mono font-bold"
                                            >
                                                S/
                                                {{ grandTaxTotal.toFixed(2) }}
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
                                    v-if="form.errors.products"
                                    class="mt-2 text-sm text-destructive"
                                >
                                    {{ form.errors.products }}
                                </p>
                            </CardContent>
                        </Card>
                    </form>
                </div>

                <!-- Activity Log Sidebar (Right - 1/3) -->
                <div class="lg:col-span-1">
                    <Card class="sticky top-4">
                        <CardHeader>
                            <CardTitle>Historial de Cambios</CardTitle>
                            <CardDescription
                                >Últimas 20 actividades</CardDescription
                            >
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
                                            {{
                                                getActivityDescription(activity)
                                            }}
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
        </div>
    </AppLayout>
</template>
