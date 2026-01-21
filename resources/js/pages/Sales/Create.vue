<script setup lang="ts">
import FormPageHeader from '@/components/FormPageHeader.vue';
import ProductCombobox from '@/components/ProductCombobox.vue';
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
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Save, Trash2 } from 'lucide-vue-next';
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

interface ProductLine {
    product_product_id: number | null;
    quantity: number;
    price: number;
    tax_id: number | null;
}

interface Props {
    customers: Partner[];
    warehouses: Warehouse[];
    taxes: Tax[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Ventas', href: '/sales' },
    { title: 'Crear', href: '/sales/create' },
];

const form = useForm({
    partner_id: null as number | null,
    warehouse_id: null as number | null,
    notes: '',
    products: [] as ProductLine[],
});

const addProductLine = () => {
    form.products.push({
        product_product_id: null,
        quantity: 1,
        price: 0,
        tax_id: props.taxes.find((t) => t.rate_percent === 18)?.id || null, // Default IGV 18%
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
    form.post('/sales');
};

// Agregar una línea por defecto al iniciar
if (form.products.length === 0) {
    addProductLine();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Crear Venta" />

        <div class="container mx-auto max-w-6xl p-4">
            <FormPageHeader
                title="Crear Venta"
                description="Nueva venta directa"
                back-href="/sales"
            >
                <template #actions>
                    <Button
                        @click="submit"
                        :disabled="
                            form.processing || form.products.length === 0
                        "
                    >
                        <Save class="mr-2 h-4 w-4" />
                        {{
                            form.processing
                                ? 'Guardando...'
                                : 'Guardar como Borrador'
                        }}
                    </Button>
                </template>
            </FormPageHeader>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Información General -->
                <Card>
                    <CardHeader>
                        <CardTitle>Información General</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-4 md:grid-cols-2">
                        <!-- Cliente -->
                        <div class="space-y-2">
                            <Label for="partner_id">Cliente</Label>
                            <Select v-model="form.partner_id">
                                <SelectTrigger id="partner_id">
                                    <SelectValue
                                        placeholder="Seleccionar cliente (Opcional)"
                                    />
                                </SelectTrigger>
                                <SelectContent>
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
                                v-if="form.errors.partner_id"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.partner_id }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Dejar en blanco para cliente público/general
                            </p>
                        </div>

                        <!-- Almacén -->
                        <div class="space-y-2">
                            <Label for="warehouse_id">Almacén *</Label>
                            <Select v-model="form.warehouse_id">
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

                        <!-- Notas -->
                        <div class="space-y-2 md:col-span-2">
                            <Label for="notes">Notas / Observaciones</Label>
                            <Textarea
                                id="notes"
                                v-model="form.notes"
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
                                    <!-- Producto -->
                                    <TableCell>
                                        <ProductCombobox
                                            v-model="line.product_product_id"
                                            :warehouse-id="form.warehouse_id"
                                            placeholder="Buscar producto..."
                                            @select="
                                                (product) => {
                                                    // Auto-llenar con el PRECIO DE VENTA
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

                                    <!-- Subtotal -->
                                    <TableCell class="text-right font-mono">
                                        {{
                                            calculateLineSubtotal(line).toFixed(
                                                2,
                                            )
                                        }}
                                    </TableCell>

                                    <!-- IGV -->
                                    <TableCell class="text-right font-mono">
                                        {{ calculateLineTax(line).toFixed(2) }}
                                    </TableCell>

                                    <!-- Total -->
                                    <TableCell
                                        class="text-right font-mono font-medium"
                                    >
                                        {{
                                            calculateLineTotal(line).toFixed(2)
                                        }}
                                    </TableCell>

                                    <!-- Acciones -->
                                    <TableCell>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            @click="removeProductLine(index)"
                                            :disabled="
                                                form.products.length === 1
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
                            v-if="form.errors.products"
                            class="mt-2 text-sm text-destructive"
                        >
                            {{ form.errors.products }}
                        </p>
                    </CardContent>
                </Card>
            </form>
        </div>
    </AppLayout>
</template>
