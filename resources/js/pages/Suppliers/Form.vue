<script setup lang="ts">
import PartnerLookupField from '@/components/PartnerLookupField.vue';
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
import { Textarea } from '@/components/ui/textarea';
import { usePartnerLookup } from '@/composables/usePartnerLookup';
import axios from 'axios';
import { Clock, User as UserIcon } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

type Mode = 'create' | 'edit';

interface Company {
    id: number;
    trade_name: string;
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

interface Supplier {
    id: number;
    company_id: number | null;
    document_type: string;
    document_number: string;
    business_name: string | null;
    first_name: string | null;
    last_name: string | null;
    email: string | null;
    phone: string | null;
    mobile: string | null;
    address: string | null;
    district: string | null;
    province: string | null;
    department: string | null;
    payment_terms: number | null;
    provider_category: string | null;
    notes: string | null;
    status: string;
    created_at: string;
    updated_at: string;
}

type ErrorBag = Record<string, string[]>;

const props = defineProps<{
    mode: Mode;
    supplierId?: number | null;
    initialName?: string;
}>();

const emit = defineEmits<{
    (e: 'loaded', supplier: Supplier): void;
    (e: 'saved', supplier: Supplier): void;
}>();

const { handlePartnerFound } = usePartnerLookup();

const supplier = ref<Supplier | null>(null);
const activities = ref<Activity[]>([]);
const companies = ref<Company[]>([]);
const processing = ref(false);
const errors = ref<ErrorBag>({});

const form = ref({
    company_id: undefined as number | undefined,
    document_type: 'RUC',
    document_number: '',
    business_name: '',
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    mobile: '',
    address: '',
    district: '',
    province: '',
    department: '',
    payment_terms: undefined as number | undefined,
    supplier_category: '',
    notes: '',
    status: 'active',
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

const displayName = computed(() => {
    if (form.value.document_type === 'RUC' && form.value.business_name) {
        return form.value.business_name;
    }
    const name = `${form.value.first_name} ${form.value.last_name}`.trim();
    return name || 'Proveedor';
});

const onPartnerFound = (data: any) => {
    handlePartnerFound(data, form.value as any);
};

const loadFormOptions = async () => {
    const response = await axios.get('/api/suppliers/form-options', {
        headers,
    });
    companies.value = (response.data?.data?.companies || []) as Company[];
};

const loadSupplier = async () => {
    if (props.mode !== 'edit' || !props.supplierId) return;

    const response = await axios.get(`/api/suppliers/${props.supplierId}`, {
        headers,
    });

    supplier.value = response.data?.data as Supplier;
    activities.value = (response.data?.meta?.activities || []) as Activity[];

    if (supplier.value) {
        form.value = {
            company_id: supplier.value.company_id ?? undefined,
            document_type: supplier.value.document_type,
            document_number: supplier.value.document_number,
            business_name: supplier.value.business_name || '',
            first_name: supplier.value.first_name || '',
            last_name: supplier.value.last_name || '',
            email: supplier.value.email || '',
            phone: supplier.value.phone || '',
            mobile: supplier.value.mobile || '',
            address: supplier.value.address || '',
            district: supplier.value.district || '',
            province: supplier.value.province || '',
            department: supplier.value.department || '',
            payment_terms:
                supplier.value.payment_terms !== null
                    ? Number(supplier.value.payment_terms)
                    : undefined,
            supplier_category: supplier.value.provider_category || '',
            notes: supplier.value.notes || '',
            status: supplier.value.status || 'active',
        };
        emit('loaded', supplier.value);
    }
};

const submit = async () => {
    processing.value = true;
    errors.value = {};

    try {
        const payload = {
            company_id: form.value.company_id ?? null,
            document_type: form.value.document_type,
            document_number: form.value.document_number,
            business_name:
                form.value.document_type === 'RUC'
                    ? form.value.business_name || null
                    : null,
            first_name:
                form.value.document_type !== 'RUC'
                    ? form.value.first_name || null
                    : null,
            last_name:
                form.value.document_type !== 'RUC'
                    ? form.value.last_name || null
                    : null,
            email: form.value.email || null,
            phone: form.value.phone || null,
            mobile: form.value.mobile || null,
            address: form.value.address || null,
            district: form.value.district || null,
            province: form.value.province || null,
            department: form.value.department || null,
            payment_terms:
                form.value.payment_terms === undefined ||
                Number.isNaN(form.value.payment_terms as any)
                    ? null
                    : Number(form.value.payment_terms),
            supplier_category: form.value.supplier_category || null,
            notes: form.value.notes || null,
            ...(props.mode === 'edit' ? { status: form.value.status } : {}),
        };

        if (props.mode === 'create') {
            const response = await axios.post('/api/suppliers', payload, {
                headers,
            });
            const saved = response.data?.data as Supplier;
            supplier.value = saved;
            emit('saved', saved);
            return;
        }

        if (!props.supplierId) return;

        const response = await axios.put(
            `/api/suppliers/${props.supplierId}`,
            payload,
            { headers },
        );
        const saved = response.data?.data as Supplier;
        supplier.value = saved;
        emit('saved', saved);
    } catch (e: any) {
        if (e?.response?.status === 422) {
            errors.value = (e.response.data?.errors || {}) as ErrorBag;
        } else {
            console.error('Error saving supplier:', e);
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

defineExpose({
    submit,
    processing,
});

onMounted(async () => {
    try {
        if (props.mode === 'create' && props.initialName) {
            const name = props.initialName.trim();
            if (name) {
                if (
                    !form.value.business_name &&
                    form.value.document_type === 'RUC'
                ) {
                    form.value.business_name = name;
                }
                if (
                    !form.value.first_name &&
                    !form.value.last_name &&
                    form.value.document_type !== 'RUC'
                ) {
                    form.value.first_name = name;
                }
            }
        }
        await loadFormOptions();
        await loadSupplier();
    } catch (e) {
        console.error('Error loading supplier form:', e);
    }
});
</script>

<template>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <form @submit.prevent="submit" class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Información del Proveedor</CardTitle>
                        <CardDescription>Datos principales</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <PartnerLookupField
                            v-model:document-type="form.document_type"
                            v-model:document-number="form.document_number"
                            :error="errorText('document_number') || undefined"
                            :auto-lookup="props.mode === 'create'"
                            @found="onPartnerFound"
                        />

                        <div v-if="form.document_type === 'RUC'">
                            <Label for="business_name">Razón Social *</Label>
                            <Input
                                id="business_name"
                                v-model="form.business_name"
                                :class="{
                                    'border-red-500':
                                        errorText('business_name'),
                                }"
                            />
                            <p
                                v-if="errorText('business_name')"
                                class="mt-1 text-sm text-red-500"
                            >
                                {{ errorText('business_name') }}
                            </p>
                        </div>

                        <div
                            v-if="form.document_type !== 'RUC'"
                            class="grid grid-cols-2 gap-4"
                        >
                            <div>
                                <Label for="first_name">Nombres *</Label>
                                <Input
                                    id="first_name"
                                    v-model="form.first_name"
                                    :class="{
                                        'border-red-500':
                                            errorText('first_name'),
                                    }"
                                />
                                <p
                                    v-if="errorText('first_name')"
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{ errorText('first_name') }}
                                </p>
                            </div>

                            <div>
                                <Label for="last_name">Apellidos *</Label>
                                <Input
                                    id="last_name"
                                    v-model="form.last_name"
                                    :class="{
                                        'border-red-500':
                                            errorText('last_name'),
                                    }"
                                />
                                <p
                                    v-if="errorText('last_name')"
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{ errorText('last_name') }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <Label for="company_id">Compañía/Sucursal</Label>
                            <Select v-model="form.company_id">
                                <SelectTrigger
                                    :class="{
                                        'border-red-500':
                                            errorText('company_id'),
                                    }"
                                >
                                    <SelectValue
                                        placeholder="Seleccionar compañía..."
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="company in companies"
                                        :key="company.id"
                                        :value="company.id"
                                    >
                                        {{ company.trade_name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p
                                v-if="errorText('company_id')"
                                class="mt-1 text-sm text-red-500"
                            >
                                {{ errorText('company_id') }}
                            </p>
                        </div>

                        <div v-if="props.mode === 'edit'">
                            <Label for="status">Estado *</Label>
                            <Select v-model="form.status">
                                <SelectTrigger
                                    :class="{
                                        'border-red-500': errorText('status'),
                                    }"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="active"
                                        >Activo</SelectItem
                                    >
                                    <SelectItem value="inactive"
                                        >Inactivo</SelectItem
                                    >
                                    <SelectItem value="suspended"
                                        >Suspendido</SelectItem
                                    >
                                    <SelectItem value="blacklisted"
                                        >Bloqueado</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <p
                                v-if="errorText('status')"
                                class="mt-1 text-sm text-red-500"
                            >
                                {{ errorText('status') }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Contacto y Dirección</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <Label for="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    v-model="form.email"
                                />
                            </div>
                            <div>
                                <Label for="phone">Teléfono</Label>
                                <Input id="phone" v-model="form.phone" />
                            </div>
                            <div>
                                <Label for="mobile">Celular</Label>
                                <Input id="mobile" v-model="form.mobile" />
                            </div>
                        </div>

                        <div>
                            <Label for="address">Dirección</Label>
                            <Input id="address" v-model="form.address" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <Label for="district">Distrito</Label>
                                <Input id="district" v-model="form.district" />
                            </div>
                            <div>
                                <Label for="province">Provincia</Label>
                                <Input id="province" v-model="form.province" />
                            </div>
                            <div>
                                <Label for="department">Departamento</Label>
                                <Input
                                    id="department"
                                    v-model="form.department"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Información Adicional</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <Label for="payment_terms"
                                    >Condiciones de Pago (días)</Label
                                >
                                <Input
                                    id="payment_terms"
                                    v-model.number="form.payment_terms"
                                    type="number"
                                    min="0"
                                    step="1"
                                    placeholder="Ej: 30"
                                />
                            </div>
                            <div>
                                <Label for="supplier_category">Categoría</Label>
                                <Input
                                    id="supplier_category"
                                    v-model="form.supplier_category"
                                    placeholder="Ej: Insumos, Servicios, etc."
                                />
                            </div>
                        </div>
                        <div>
                            <Label for="notes">Notas Internas</Label>
                            <Textarea
                                id="notes"
                                v-model="form.notes"
                                placeholder="Información relevante del proveedor..."
                            />
                        </div>
                    </CardContent>
                </Card>
            </form>
        </div>

        <div class="lg:col-span-1">
            <Card class="sticky top-4">
                <CardHeader>
                    <CardTitle>Historial de Cambios</CardTitle>
                    <CardDescription v-if="props.mode === 'edit'">
                        {{ displayName }}
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
                                <Clock class="h-4 w-4 text-muted-foreground" />
                            </div>
                            <div class="flex-1 space-y-1">
                                <p class="font-medium">
                                    {{ activity.description }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ formatDate(activity.created_at) }}
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
                            No hay actividades registradas
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
