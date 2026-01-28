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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CheckCircle,
    Clock,
    FilePlus,
    MoreVertical,
    Plus,
    Save,
    Send,
    Trash2,
    User,
    XCircle,
} from 'lucide-vue-next';
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

interface Sale {
    id: number;
    status: 'draft' | 'posted' | 'cancelled';
    partner_id: number | null;
    warehouse_id: number;
    notes: string | null;
    products: Productable[]; // Note: controller returns 'products' relationship
    sunat_status?: string;
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

interface RelatedSaleLink {
    id: number;
    document: string;
    status: 'draft' | 'posted' | 'cancelled';
    doc_type: string;
    journal_code?: string | null;
    partner_name?: string | null;
}

interface Props {
    sale: Sale;
    activities?: Activity[];
    customers: Partner[];
    warehouses: Warehouse[];
    taxes: Tax[];
    originSale?: RelatedSaleLink | null;
    creditNotes?: RelatedSaleLink[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Ventas', href: '/sales' },
    { title: 'Editar', href: `/sales/${props.sale.id}/edit` },
];

// Pre-llenar formulario con datos existentes
const form = useForm({
    partner_id: props.sale.partner_id,
    warehouse_id: props.sale.warehouse_id,
    notes: props.sale.notes || '',
    products: props.sale.products.map((p) => ({
        product_product_id: p.product_product_id,
        quantity: p.quantity,
        price: p.price,
        tax_id: p.tax_id,
    })) as ProductLine[],
});

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
    form.put(`/sales/${props.sale.id}`);
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
        return 'Venta Creada';
    }

    // Para eventos de actualización, interpretar cambios
    if (activity.event === 'updated' && activity.properties) {
        const attributes = activity.properties.attributes || {};
        const old = activity.properties.old || {};

        // Detectar cambio de status
        if (attributes.status && old.status) {
            if (attributes.status === 'posted') return 'Venta Publicada';
            if (attributes.status === 'cancelled') return 'Venta Cancelada';
            if (attributes.status === 'draft') return 'Revertida a Borrador';
        }

        // Otros cambios
        const changes = [];
        if (attributes.partner_id !== undefined) changes.push('cliente');
        if (attributes.warehouse_id !== undefined) changes.push('almacén');
        if (attributes.total !== undefined) changes.push('total');
        if (attributes.notes !== undefined) changes.push('notas');

        if (changes.length > 0) {
            return `Actualizado: ${changes.join(', ')}`;
        }
    }

    return activity.description || 'Actualización';
};

// Determinar si la venta es editable (solo drafts permiten editar productos)
const isEditable = computed(() => {
    return props.sale.status === 'draft';
});

const docTypeLabel = (code?: string | null) => {
    if (code === '03') return 'Boleta';
    if (code === '01') return 'Factura';
    return code || '';
};

const canSendSunat = computed(() => {
    if (props.sale.journal?.is_fiscal === false) return false;
    if (props.sale.status !== 'posted') return false;
    if (props.sale.sunat_response?.accepted === true) return false;
    return props.sale.sunat_status !== 'accepted';
});

const canCreateCreditNote = computed(() => {
    if (props.sale.status !== 'posted') return false;
    if (props.sale.original_sale_id) return false;
    const docType = props.sale.journal?.document_type_code;
    return docType === '01' || docType === '03';
});

const submitDisabled = computed(() => {
    if (form.processing) return true;
    if (isEditable.value) return form.products.length === 0;
    return false;
});

const postThisSale = () => {
    if (
        confirm(
            '¿Estás seguro de publicar este documento? Se reducirá el inventario si aplica.',
        )
    ) {
        router.post(
            `/sales/${props.sale.id}/post`,
            {},
            { preserveScroll: true },
        );
    }
};

const cancelThisSale = () => {
    if (
        confirm(
            '¿Estás seguro de cancelar esta venta? Se devolverá el stock al inventario.',
        )
    ) {
        router.post(
            `/sales/${props.sale.id}/cancel`,
            {},
            { preserveScroll: true },
        );
    }
};

const sendSunat = () => {
    router.post(
        `/sales/${props.sale.id}/sunat/retry`,
        {},
        { preserveScroll: true },
    );
};

const createCreditNoteAction = () => {
    router.post(
        `/sales/${props.sale.id}/credit-note`,
        {},
        { preserveScroll: true },
    );
};

const deleteThisSale = () => {
    if (confirm('¿Eliminar este borrador? Esta acción no se puede deshacer.')) {
        router.delete(`/sales/${props.sale.id}`);
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Editar Venta #${sale.id}`" />

        <div class="container mx-auto max-w-6xl p-4">
            <FormPageHeader
                title="Editar Venta"
                :description="`Venta #${sale.id}`"
                back-href="/sales"
            >
                <template #actions>
                    <Button @click="submit" :disabled="submitDisabled">
                        <Save class="mr-2 h-4 w-4" />
                        Guardar
                    </Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                title="Acciones"
                            >
                                <MoreVertical class="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                v-if="sale.status === 'draft'"
                                @click="postThisSale"
                            >
                                <CheckCircle class="mr-2 h-4 w-4" />
                                Publicar
                            </DropdownMenuItem>

                            <DropdownMenuItem
                                v-if="
                                    sale.status === 'posted' &&
                                    !sale.original_sale_id
                                "
                                @click="cancelThisSale"
                            >
                                <XCircle class="mr-2 h-4 w-4" />
                                Cancelar
                            </DropdownMenuItem>

                            <DropdownMenuItem
                                v-if="canSendSunat"
                                @click="sendSunat"
                            >
                                <Send class="mr-2 h-4 w-4" />
                                Enviar SUNAT
                            </DropdownMenuItem>

                            <DropdownMenuItem
                                v-if="canCreateCreditNote"
                                @click="createCreditNoteAction"
                            >
                                <FilePlus class="mr-2 h-4 w-4" />
                                Nota de Crédito
                            </DropdownMenuItem>

                            <DropdownMenuSeparator
                                v-if="sale.status === 'draft'"
                            />

                            <DropdownMenuItem
                                v-if="sale.status === 'draft'"
                                class="text-destructive focus:text-destructive"
                                @click="deleteThisSale"
                            >
                                <Trash2 class="mr-2 h-4 w-4" />
                                Eliminar
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>
            </FormPageHeader>

            <!-- Alert para ventas publicadas -->
            <Alert v-if="!isEditable" class="mb-6">
                <AlertTitle>Venta Publicada</AlertTitle>
                <AlertDescription>
                    Esta venta ya está publicada. Solo puedes editar las notas.
                    Para modificar productos, primero debes revertir el estado
                    (si estuviera permitido) o cancelar y crear una nueva.
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
                                <!-- Cliente -->
                                <div class="space-y-2">
                                    <Label for="partner_id">Cliente</Label>
                                    <Select
                                        v-model="form.partner_id"
                                        :disabled="!isEditable"
                                    >
                                        <SelectTrigger id="partner_id">
                                            <SelectValue
                                                placeholder="Seleccionar cliente"
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

                                <!-- Notas -->
                                <div class="space-y-2 md:col-span-2">
                                    <Label for="notes"
                                        >Notas / Observaciones</Label
                                    >
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
                                                            // Auto-llenar con el PRECIO DE VENTA
                                                            line.price =
                                                                product.price ||
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

                        <Card
                            v-if="
                                props.sale.original_sale_id && props.originSale
                            "
                        >
                            <CardHeader>
                                <CardTitle>Documento Origen</CardTitle>
                            </CardHeader>
                            <CardContent
                                class="flex items-center justify-between gap-3"
                            >
                                <div class="text-sm">
                                    <div class="text-muted-foreground">
                                        Esta Nota de Crédito viene de:
                                    </div>
                                    <div class="font-medium">
                                        {{
                                            docTypeLabel(
                                                props.originSale.doc_type,
                                            )
                                        }}
                                        {{ props.originSale.document }}
                                        <span
                                            v-if="props.originSale.partner_name"
                                            class="text-muted-foreground"
                                        >
                                            ·
                                            {{ props.originSale.partner_name }}
                                        </span>
                                    </div>
                                </div>
                                <a
                                    class="text-sm underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                    :href="`/sales/${props.originSale.id}/edit`"
                                    @click.prevent="
                                        router.visit(
                                            `/sales/${props.originSale.id}/edit`,
                                        )
                                    "
                                >
                                    Ver origen
                                </a>
                            </CardContent>
                        </Card>

                        <Card
                            v-else-if="
                                props.creditNotes &&
                                props.creditNotes.length > 0
                            "
                        >
                            <CardHeader>
                                <CardTitle>Notas de Crédito</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div class="text-sm text-muted-foreground">
                                    Notas de crédito creadas desde este
                                    documento:
                                </div>
                                <div class="space-y-2">
                                    <div
                                        v-for="note in props.creditNotes"
                                        :key="note.id"
                                        class="flex items-center justify-between gap-3 rounded-md border p-2"
                                    >
                                        <div class="text-sm">
                                            <div class="font-medium">
                                                {{
                                                    docTypeLabel(note.doc_type)
                                                }}
                                                {{ note.document }}
                                            </div>
                                            <div
                                                class="text-xs text-muted-foreground"
                                            >
                                                Estado: {{ note.status }}
                                            </div>
                                        </div>
                                        <a
                                            class="text-sm underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                            :href="`/sales/${note.id}/edit`"
                                            @click.prevent="
                                                router.visit(
                                                    `/sales/${note.id}/edit`,
                                                )
                                            "
                                        >
                                            Ver nota
                                        </a>
                                    </div>
                                </div>
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
                                    v-if="
                                        !activities || activities.length === 0
                                    "
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
