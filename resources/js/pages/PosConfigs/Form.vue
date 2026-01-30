<script setup lang="ts">
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
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import axios from 'axios';
import { Clock, Plus, User, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

type Mode = 'create' | 'edit';

interface Tax {
    id: number;
    name: string;
    rate_percent: number;
}

interface Warehouse {
    id: number;
    name: string;
}

interface Customer {
    id: number;
    display_name: string;
}

interface Journal {
    id: number;
    name: string;
    code: string;
}

interface PosConfigJournalRow {
    journal_id: number | null;
    document_type: string;
    is_default: boolean;
}

interface PosConfig {
    id: number;
    name: string;
    warehouse_id: number;
    default_customer_id: number | null;
    tax_id: number | null;
    apply_tax: boolean;
    prices_include_tax: boolean;
    is_active: boolean;
    journals: Array<{
        id: number;
        pivot: {
            document_type: string;
            is_default: boolean;
        };
    }>;
}

interface Activity {
    id: number;
    description: string;
    properties: {
        attributes?: Record<string, any>;
        old?: Record<string, any>;
    };
    created_at: string;
    causer?: {
        id: number;
        name: string;
    } | null;
}

type ErrorBag = Record<string, string[]>;

const props = defineProps<{
    mode: Mode;
    posConfigId?: number | null;
}>();

const emit = defineEmits<{
    (e: 'loaded', posConfig: PosConfig): void;
    (e: 'saved', posConfig: PosConfig): void;
}>();

const headers = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

const isEditing = computed(() => props.mode === 'edit');

const warehouses = ref<Warehouse[]>([]);
const customers = ref<Customer[]>([]);
const taxes = ref<Tax[]>([]);
const journals = ref<Journal[]>([]);

const activities = ref<Activity[]>([]);
const posConfig = ref<PosConfig | null>(null);

const processing = ref(false);
const errors = ref<ErrorBag>({});

const form = ref({
    name: '',
    warehouse_id: null as number | null,
    default_customer_id: null as number | null,
    tax_id: null as number | null,
    apply_tax: true,
    prices_include_tax: false,
    is_active: true,
    journals: [] as PosConfigJournalRow[],
});

const documentTypes = [
    { value: 'invoice', label: 'Factura' },
    { value: 'receipt', label: 'Boleta' },
    { value: 'credit_note', label: 'Nota de Crédito' },
    { value: 'debit_note', label: 'Nota de Débito' },
];

const errorText = (key: string): string | null => {
    const value = errors.value[key];
    if (!value) return null;
    return Array.isArray(value) ? value[0] : String(value);
};

const addJournal = () => {
    form.value.journals.push({
        journal_id: null,
        document_type: 'invoice',
        is_default: false,
    });
};

const removeJournal = (index: number) => {
    form.value.journals.splice(index, 1);
};

const loadFormOptions = async () => {
    const response = await axios.get('/api/pos-configs/form-options', {
        headers,
    });
    warehouses.value = (response.data?.data?.warehouses || []) as Warehouse[];
    customers.value = (response.data?.data?.customers || []) as Customer[];
    taxes.value = (response.data?.data?.taxes || []) as Tax[];
    journals.value = (response.data?.data?.journals || []) as Journal[];
};

const loadPosConfig = async () => {
    if (!isEditing.value || !props.posConfigId) return;

    const response = await axios.get(`/api/pos-configs/${props.posConfigId}`, {
        headers,
    });

    posConfig.value = response.data?.data as PosConfig;
    activities.value = (response.data?.meta?.activities || []) as Activity[];

    if (posConfig.value) {
        form.value = {
            name: posConfig.value.name || '',
            warehouse_id: posConfig.value.warehouse_id ?? null,
            default_customer_id: posConfig.value.default_customer_id ?? null,
            tax_id: posConfig.value.tax_id ?? null,
            apply_tax: posConfig.value.apply_tax ?? true,
            prices_include_tax: posConfig.value.prices_include_tax ?? false,
            is_active: posConfig.value.is_active ?? true,
            journals: (posConfig.value.journals || []).map((j) => ({
                journal_id: j.id,
                document_type: j.pivot.document_type,
                is_default: j.pivot.is_default,
            })),
        };
        emit('loaded', posConfig.value);
    }
};

const submit = async () => {
    processing.value = true;
    errors.value = {};

    try {
        const payload = {
            name: form.value.name,
            warehouse_id: form.value.warehouse_id,
            default_customer_id: form.value.default_customer_id,
            tax_id: form.value.apply_tax ? form.value.tax_id : null,
            apply_tax: form.value.apply_tax,
            prices_include_tax: form.value.prices_include_tax,
            is_active: form.value.is_active,
            journals: form.value.journals
                .filter((j) => !!j.journal_id)
                .map((j) => ({
                    journal_id: j.journal_id,
                    document_type: j.document_type,
                    is_default: !!j.is_default,
                })),
        };

        if (props.mode === 'create') {
            const response = await axios.post('/api/pos-configs', payload, {
                headers,
            });
            const saved = response.data?.data as PosConfig;
            posConfig.value = saved;
            emit('saved', saved);
            return;
        }

        if (!props.posConfigId) return;

        const response = await axios.put(
            `/api/pos-configs/${props.posConfigId}`,
            payload,
            { headers },
        );
        const saved = response.data?.data as PosConfig;
        posConfig.value = saved;
        emit('saved', saved);

        await loadPosConfig();
    } catch (e: any) {
        if (e?.response?.status === 422) {
            errors.value = (e.response.data?.errors || {}) as ErrorBag;
        } else {
            console.error('Error saving POS config:', e);
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

const getActivityDescription = (activity: Activity): string => {
    const props = activity.properties;

    if (activity.description === 'created') {
        return 'POS Creado';
    }

    if (activity.description === 'updated' && props.attributes) {
        const changes = Object.keys(props.attributes).filter(
            (key) => props.old && props.attributes![key] !== props.old[key],
        );

        if (changes.length === 0) return 'Actualizado';

        const fieldNames: Record<string, string> = {
            name: 'nombre',
            warehouse_id: 'almacén',
            default_customer_id: 'cliente por defecto',
            tax_id: 'impuesto',
            apply_tax: 'aplicar impuesto',
            prices_include_tax: 'precios incluyen impuesto',
            is_active: 'estado',
        };

        if (changes.includes('is_active')) {
            return props.attributes.is_active
                ? 'POS Activado'
                : 'POS Desactivado';
        }

        const changedFields = changes
            .map((key) => fieldNames[key] || key)
            .join(', ');
        return `Actualizado: ${changedFields}`;
    }

    return activity.description;
};

defineExpose({
    submit,
    processing,
});

onMounted(async () => {
    try {
        await loadFormOptions();
        await loadPosConfig();
    } catch (e) {
        console.error('Error loading POS config form:', e);
    }
});
</script>

<template>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <form @submit.prevent="submit" class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Información General</CardTitle>
                        <CardDescription
                            >Datos base y comportamiento del
                            POS</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Nombre *</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="Ej: POS Principal"
                                :class="{
                                    'border-destructive': errorText('name'),
                                }"
                                required
                            />
                            <p
                                v-if="errorText('name')"
                                class="text-sm text-destructive"
                            >
                                {{ errorText('name') }}
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="warehouse_id">Almacén *</Label>
                                <Select
                                    :model-value="
                                        form.warehouse_id?.toString() || ''
                                    "
                                    @update:model-value="
                                        (value) =>
                                            (form.warehouse_id = value
                                                ? parseInt(value as string)
                                                : null)
                                    "
                                    required
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            placeholder="Seleccionar almacén"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="warehouse in warehouses"
                                            :key="warehouse.id"
                                            :value="warehouse.id.toString()"
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
                                <Label for="default_customer_id"
                                    >Cliente por Defecto</Label
                                >
                                <Select
                                    :model-value="
                                        form.default_customer_id?.toString() ||
                                        ''
                                    "
                                    @update:model-value="
                                        (value) =>
                                            (form.default_customer_id = value
                                                ? parseInt(value as string)
                                                : null)
                                    "
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            placeholder="Seleccionar cliente"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="customer in customers"
                                            :key="customer.id"
                                            :value="customer.id.toString()"
                                        >
                                            {{ customer.display_name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="tax_id">Impuesto</Label>
                            <Select
                                :disabled="!form.apply_tax"
                                :model-value="form.tax_id?.toString() || ''"
                                @update:model-value="
                                    (value) =>
                                        (form.tax_id = value
                                            ? parseInt(value as string)
                                            : null)
                                "
                            >
                                <SelectTrigger>
                                    <SelectValue
                                        placeholder="Seleccionar impuesto"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="tax in taxes"
                                        :key="tax.id"
                                        :value="tax.id.toString()"
                                    >
                                        {{ tax.name }} ({{ tax.rate_percent }}%)
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-3 md:grid-cols-3">
                            <div
                                class="flex items-center justify-between gap-4 rounded-md border p-3"
                            >
                                <div class="min-w-0 space-y-0.5">
                                    <Label
                                        for="apply_tax"
                                        class="cursor-pointer"
                                    >
                                        Aplicar Impuesto
                                    </Label>
                                    <p class="text-xs text-muted-foreground">
                                        Habilita impuestos en ventas
                                    </p>
                                </div>
                                <Switch
                                    id="apply_tax"
                                    v-model:checked="form.apply_tax"
                                />
                            </div>

                            <div
                                class="flex items-center justify-between gap-4 rounded-md border p-3"
                            >
                                <div class="min-w-0 space-y-0.5">
                                    <Label
                                        for="prices_include_tax"
                                        class="cursor-pointer"
                                    >
                                        Precios con Impuesto
                                    </Label>
                                    <p class="text-xs text-muted-foreground">
                                        Incluye impuesto en el precio
                                    </p>
                                </div>
                                <Switch
                                    id="prices_include_tax"
                                    v-model:checked="form.prices_include_tax"
                                />
                            </div>

                            <div
                                class="flex items-center justify-between gap-4 rounded-md border p-3"
                            >
                                <div class="min-w-0 space-y-0.5">
                                    <Label
                                        for="is_active"
                                        class="cursor-pointer"
                                    >
                                        POS Activo
                                    </Label>
                                    <p class="text-xs text-muted-foreground">
                                        Permite operar el POS
                                    </p>
                                </div>
                                <Switch
                                    id="is_active"
                                    v-model:checked="form.is_active"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div class="space-y-1">
                                <CardTitle>Diarios Asociados</CardTitle>
                                <CardDescription>
                                    Relación de diarios por tipo de documento
                                </CardDescription>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="addJournal"
                            >
                                <Plus class="mr-2 h-4 w-4" />
                                Agregar Diario
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="form.journals.length > 0"
                            class="rounded-md border"
                        >
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Diario</TableHead>
                                        <TableHead>Tipo de Documento</TableHead>
                                        <TableHead>Por Defecto</TableHead>
                                        <TableHead class="w-[50px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="(
                                            journal, index
                                        ) in form.journals"
                                        :key="index"
                                    >
                                        <TableCell>
                                            <Select
                                                :model-value="
                                                    journal.journal_id?.toString() ||
                                                    ''
                                                "
                                                @update:model-value="
                                                    (value) =>
                                                        (journal.journal_id =
                                                            value
                                                                ? parseInt(
                                                                      value as string,
                                                                  )
                                                                : null)
                                                "
                                            >
                                                <SelectTrigger>
                                                    <SelectValue
                                                        placeholder="Seleccionar diario"
                                                    />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="j in journals"
                                                        :key="j.id"
                                                        :value="j.id.toString()"
                                                    >
                                                        {{ j.name }} ({{
                                                            j.code
                                                        }})
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </TableCell>
                                        <TableCell>
                                            <Select
                                                v-model="journal.document_type"
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="type in documentTypes"
                                                        :key="type.value"
                                                        :value="type.value"
                                                    >
                                                        {{ type.label }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </TableCell>
                                        <TableCell>
                                            <Switch
                                                v-model:checked="
                                                    journal.is_default
                                                "
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                @click="removeJournal(index)"
                                            >
                                                <X class="h-4 w-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <div
                            v-else
                            class="rounded-md border py-8 text-center text-sm text-muted-foreground"
                        >
                            No hay diarios asociados. Haz click en "Agregar
                            Diario" para comenzar.
                        </div>
                    </CardContent>
                </Card>
            </form>
        </div>

        <div v-if="isEditing" class="lg:col-span-1">
            <Card class="sticky top-4">
                <CardHeader>
                    <CardTitle>Historial de Cambios</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="activities.length > 0" class="space-y-4">
                        <div
                            v-for="activity in activities"
                            :key="activity.id"
                            class="flex gap-3 border-b pb-4 last:border-b-0 last:pb-0"
                        >
                            <div class="mt-1 flex-shrink-0">
                                <Clock class="h-4 w-4 text-muted-foreground" />
                            </div>
                            <div class="flex-1 space-y-1">
                                <p class="text-sm font-medium">
                                    {{ getActivityDescription(activity) }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ formatDate(activity.created_at) }}
                                </p>
                                <div
                                    v-if="activity.causer"
                                    class="flex items-center gap-1 text-xs text-muted-foreground"
                                >
                                    <User class="h-3 w-3" />
                                    <span>{{ activity.causer.name }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        No hay historial de cambios
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
