<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import type { BreadcrumbItem } from '@/types';
import ProductCombobox from '@/components/ProductCombobox.vue';

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

interface ProductLine {
    product_product_id: number | null;
    quantity: number;
    price: number;
    tax_id: number | null;
}

interface Props {
    suppliers: Partner[];
    warehouses: Warehouse[];
    taxes: Tax[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Compras', href: '/purchases' },
    { title: 'Crear', href: '/purchases/create' },
];

const form = useForm({
    partner_id: null as number | null,
    warehouse_id: null as number | null,
    vendor_bill_number: '',
    vendor_bill_date: '',
    observation: '',
    products: [] as ProductLine[],
});

const addProductLine = () => {
    form.products.push({
        product_product_id: null,
        quantity: 1,
        price: 0,
        tax_id: props.taxes.find(t => t.rate_percent === 18)?.id || null,
    });
};

const removeProductLine = (index: number) => {
    form.products.splice(index, 1);
};

const getTaxRate = (taxId: number | null) => {
    if (!taxId) return 0;
    const tax = props.taxes.find(t => t.id === taxId);
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
    return form.products.reduce((sum, line) => sum + calculateLineTotal(line), 0);
});

const grandSubtotal = computed(() => {
    return form.products.reduce((sum, line) => sum + calculateLineSubtotal(line), 0);
});

const grandTaxTotal = computed(() => {
    return form.products.reduce((sum, line) => sum + calculateLineTax(line), 0);
});

const submit = () => {
    form.post('/purchases');
};

// Agregar una línea por defecto al iniciar
if (form.products.length === 0) {
    addProductLine();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Crear Compra" />

        <div class="container mx-auto p-4 max-w-6xl">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="icon" as-child>
                        <a href="/purchases">
                            <ArrowLeft class="h-5 w-5" />
                        </a>
                    </Button>
                    <div>
                        <h1 class="text-2xl font-bold">Crear Compra</h1>
                        <p class="text-sm text-muted-foreground">
                            Nueva compra de productos
                        </p>
                    </div>
                </div>
                <Button @click="submit" :disabled="form.processing || form.products.length === 0">
                    Guardar como Borrador
                </Button>
            </div>

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
                            <Select v-model="form.partner_id">
                                <SelectTrigger id="partner_id">
                                    <SelectValue placeholder="Seleccionar proveedor" />
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
                            <p v-if="form.errors.partner_id" class="text-sm text-destructive">
                                {{ form.errors.partner_id }}
                            </p>
                        </div>

                        <!-- Almacén -->
                        <div class="space-y-2">
                            <Label for="warehouse_id">Almacén *</Label>
                            <Select v-model="form.warehouse_id">
                                <SelectTrigger id="warehouse_id">
                                    <SelectValue placeholder="Seleccionar almacén" />
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
                            <p v-if="form.errors.warehouse_id" class="text-sm text-destructive">
                                {{ form.errors.warehouse_id }}
                            </p>
                        </div>

                        <!-- Factura del Proveedor -->
                        <div class="space-y-2">
                            <Label for="vendor_bill_number">Factura del Proveedor</Label>
                            <Input
                                id="vendor_bill_number"
                                v-model="form.vendor_bill_number"
                                placeholder="Ej: F001-192"
                            />
                            <p class="text-xs text-muted-foreground">
                                Número de la factura emitida por el proveedor
                            </p>
                        </div>

                        <!-- Fecha de Factura -->
                        <div class="space-y-2">
                            <Label for="vendor_bill_date">Fecha de Factura</Label>
                            <Input
                                id="vendor_bill_date"
                                v-model="form.vendor_bill_date"
                                type="date"
                            />
                        </div>

                        <!-- Notas -->
                        <div class="space-y-2 md:col-span-2">
                            <Label for="observation">Observaciones</Label>
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
                    <CardHeader class="flex flex-row items-center justify-between">
                        <CardTitle>Productos</CardTitle>
                        <Button type="button" @click="addProductLine" size="sm">
                            <Plus class="mr-2 h-4 w-4" />
                            Agregar Producto
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Producto</TableHead>
                                    <TableHead class="w-[120px]">Cantidad</TableHead>
                                    <TableHead class="w-[150px]">Precio Unit.</TableHead>
                                    <TableHead class="w-[150px]">Impuesto</TableHead>
                                    <TableHead class="text-right w-[120px]">Subtotal</TableHead>
                                    <TableHead class="text-right w-[100px]">IGV</TableHead>
                                    <TableHead class="text-right w-[120px]">Total</TableHead>
                                    <TableHead class="w-[50px]"></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="(line, index) in form.products" :key="index">
                                    <!-- Producto -->
                                    <TableCell>
                                        <ProductCombobox
                                            v-model="line.product_product_id"
                                            :warehouse-id="form.warehouse_id"
                                            placeholder="Buscar producto..."
                                            @select="(product) => {
                                                // Auto-llenar con el ÚLTIMO PRECIO DE COMPRA
                                                line.price = product.cost_price || 0;
                                                if (!line.quantity || line.quantity === 0) {
                                                    line.quantity = 1;
                                                }
                                            }"
                                        />
                                    </TableCell>

                                    <!-- Cantidad -->
                                    <TableCell>
                                        <Input
                                            v-model.number="line.quantity"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
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
                                            class="w-full"
                                        />
                                    </TableCell>

                                    <!-- Impuesto -->
                                    <TableCell>
                                        <Select v-model="line.tax_id">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Sin IGV" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem :value="null">Sin IGV</SelectItem>
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
                                    <TableCell class="text-right font-mono">
                                        {{ calculateLineSubtotal(line).toFixed(2) }}
                                    </TableCell>

                                    <!-- IGV -->
                                    <TableCell class="text-right font-mono">
                                        {{ calculateLineTax(line).toFixed(2) }}
                                    </TableCell>

                                    <!-- Total -->
                                    <TableCell class="text-right font-mono font-medium">
                                        {{ calculateLineTotal(line).toFixed(2) }}
                                    </TableCell>

                                    <!-- Acciones -->
                                    <TableCell>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            @click="removeProductLine(index)"
                                            :disabled="form.products.length === 1"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </TableCell>
                                </TableRow>

                                <!-- Totales -->
                                <TableRow class="bg-muted/50">
                                    <TableCell colspan="4" class="text-right font-medium">Totales:</TableCell>
                                    <TableCell class="text-right font-mono font-bold">
                                        S/ {{ grandSubtotal.toFixed(2) }}
                                    </TableCell>
                                    <TableCell class="text-right font-mono font-bold">
                                        S/ {{ grandTaxTotal.toFixed(2) }}
                                    </TableCell>
                                    <TableCell class="text-right font-mono font-bold text-lg">
                                        S/ {{ grandTotal.toFixed(2) }}
                                    </TableCell>
                                    <TableCell></TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>

                        <p v-if="form.errors.products" class="text-sm text-destructive mt-2">
                            {{ form.errors.products }}
                        </p>
                    </CardContent>
                </Card>
            </form>
        </div>
    </AppLayout>
</template>
