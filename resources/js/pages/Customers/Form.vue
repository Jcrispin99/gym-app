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

interface Customer {
    id: number;
    company_id: number | null;
    document_type: string;
    document_number: string;
    first_name: string;
    last_name: string;
    business_name: string | null;
    email: string | null;
    phone: string | null;
    mobile: string | null;
    address: string | null;
    district: string | null;
    province: string | null;
    department: string | null;
    birth_date: string | null;
    gender: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    blood_type: string | null;
    allergies: string | null;
    medical_notes: string | null;
    photo_url: string | null;
    notes: string | null;
    status: string;
    created_at: string;
    updated_at: string;
}

type ErrorBag = Record<string, string[]>;

const props = defineProps<{
    mode: Mode;
    customerId?: number | null;
}>();

const emit = defineEmits<{
    (e: 'loaded', customer: Customer): void;
    (e: 'saved', customer: Customer): void;
}>();

const { handlePartnerFound } = usePartnerLookup();

const customer = ref<Customer | null>(null);
const activities = ref<Activity[]>([]);
const companies = ref<Company[]>([]);
const processing = ref(false);
const errors = ref<ErrorBag>({});

const form = ref({
    company_id: undefined as number | undefined,
    document_type: 'DNI',
    document_number: '',
    first_name: '',
    last_name: '',
    business_name: '',
    email: '',
    phone: '',
    mobile: '',
    address: '',
    district: '',
    province: '',
    department: '',
    birth_date: '',
    gender: '' as string,
    emergency_contact_name: '',
    emergency_contact_phone: '',
    blood_type: '',
    allergies: '',
    medical_notes: '',
    photo_url: '',
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
    const name = `${form.value.first_name} ${form.value.last_name}`.trim();
    return name || 'Cliente';
});

const onPartnerFound = (data: any) => {
    handlePartnerFound(data, form.value as any);
};

const loadFormOptions = async () => {
    const response = await axios.get('/api/customers/form-options', { headers });
    companies.value = (response.data?.data?.companies || []) as Company[];
};

const loadCustomer = async () => {
    if (props.mode !== 'edit' || !props.customerId) return;

    const response = await axios.get(`/api/customers/${props.customerId}`, {
        headers,
    });

    customer.value = response.data?.data as Customer;
    activities.value = (response.data?.meta?.activities || []) as Activity[];

    if (customer.value) {
        form.value = {
            company_id: customer.value.company_id ?? undefined,
            document_type: customer.value.document_type,
            document_number: customer.value.document_number,
            first_name: customer.value.first_name || '',
            last_name: customer.value.last_name || '',
            business_name: customer.value.business_name || '',
            email: customer.value.email || '',
            phone: customer.value.phone || '',
            mobile: customer.value.mobile || '',
            address: customer.value.address || '',
            district: customer.value.district || '',
            province: customer.value.province || '',
            department: customer.value.department || '',
            birth_date: customer.value.birth_date ? String(customer.value.birth_date) : '',
            gender: customer.value.gender || '',
            emergency_contact_name: customer.value.emergency_contact_name || '',
            emergency_contact_phone: customer.value.emergency_contact_phone || '',
            blood_type: customer.value.blood_type || '',
            allergies: customer.value.allergies || '',
            medical_notes: customer.value.medical_notes || '',
            photo_url: customer.value.photo_url || '',
            notes: customer.value.notes || '',
            status: customer.value.status || 'active',
        };
        emit('loaded', customer.value);
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
            first_name: form.value.first_name,
            last_name: form.value.last_name,
            business_name: form.value.business_name || null,
            email: form.value.email || null,
            phone: form.value.phone || null,
            mobile: form.value.mobile || null,
            address: form.value.address || null,
            district: form.value.district || null,
            province: form.value.province || null,
            department: form.value.department || null,
            birth_date: form.value.birth_date || null,
            gender: form.value.gender || null,
            emergency_contact_name: form.value.emergency_contact_name || null,
            emergency_contact_phone: form.value.emergency_contact_phone || null,
            blood_type: form.value.blood_type || null,
            allergies: form.value.allergies || null,
            medical_notes: form.value.medical_notes || null,
            photo_url: form.value.photo_url || null,
            notes: form.value.notes || null,
            ...(props.mode === 'edit' ? { status: form.value.status } : {}),
        };

        if (props.mode === 'create') {
            const response = await axios.post('/api/customers', payload, {
                headers,
            });
            const saved = response.data?.data as Customer;
            customer.value = saved;
            emit('saved', saved);
            return;
        }

        if (!props.customerId) return;

        const response = await axios.put(
            `/api/customers/${props.customerId}`,
            payload,
            { headers },
        );
        const saved = response.data?.data as Customer;
        customer.value = saved;
        emit('saved', saved);
    } catch (e: any) {
        if (e?.response?.status === 422) {
            errors.value = (e.response.data?.errors || {}) as ErrorBag;
        } else {
            console.error('Error saving customer:', e);
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
        await loadFormOptions();
        await loadCustomer();
    } catch (e) {
        console.error('Error loading customer form:', e);
    }
});
</script>

<template>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <form @submit.prevent="submit" class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Información del Cliente</CardTitle>
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

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <Label for="first_name">Nombres *</Label>
                                <Input
                                    id="first_name"
                                    v-model="form.first_name"
                                    :class="{
                                        'border-red-500': errorText('first_name'),
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
                                        'border-red-500': errorText('last_name'),
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
                                        'border-red-500': errorText('company_id'),
                                    }"
                                >
                                    <SelectValue placeholder="Seleccionar compañía..." />
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
                                    <SelectValue placeholder="Seleccionar estado" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="active">Activo</SelectItem>
                                    <SelectItem value="inactive">Inactivo</SelectItem>
                                    <SelectItem value="suspended">Suspendido</SelectItem>
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
                        <CardTitle>Datos Personales</CardTitle>
                        <CardDescription>Información adicional</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 md:grid-cols-3">
                        <div>
                            <Label for="birth_date">Fecha Nacimiento</Label>
                            <Input id="birth_date" type="date" v-model="form.birth_date" />
                            <p v-if="errorText('birth_date')" class="mt-1 text-sm text-red-500">
                                {{ errorText('birth_date') }}
                            </p>
                        </div>

                        <div>
                            <Label for="gender">Género</Label>
                            <Select v-model="form.gender">
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="M">Masculino</SelectItem>
                                    <SelectItem value="F">Femenino</SelectItem>
                                    <SelectItem value="Other">Otro</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="errorText('gender')" class="mt-1 text-sm text-red-500">
                                {{ errorText('gender') }}
                            </p>
                        </div>

                        <div>
                            <Label for="blood_type">Tipo Sangre</Label>
                            <Input id="blood_type" v-model="form.blood_type" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Contacto</CardTitle>
                        <CardDescription>Datos de contacto</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 md:grid-cols-2">
                        <div>
                            <Label for="email">Email</Label>
                            <Input id="email" v-model="form.email" />
                        </div>
                        <div>
                            <Label for="phone">Teléfono</Label>
                            <Input id="phone" v-model="form.phone" />
                        </div>
                        <div>
                            <Label for="mobile">Celular</Label>
                            <Input id="mobile" v-model="form.mobile" />
                        </div>
                        <div>
                            <Label for="photo_url">Foto (URL)</Label>
                            <Input id="photo_url" v-model="form.photo_url" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Dirección</CardTitle>
                        <CardDescription>Ubicación</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <Label for="address">Dirección</Label>
                            <Input id="address" v-model="form.address" />
                        </div>
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
                            <Input id="department" v-model="form.department" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Emergencia y Salud</CardTitle>
                        <CardDescription>Contacto y notas médicas</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 md:grid-cols-2">
                        <div>
                            <Label for="emergency_contact_name">Contacto Emergencia</Label>
                            <Input id="emergency_contact_name" v-model="form.emergency_contact_name" />
                        </div>
                        <div>
                            <Label for="emergency_contact_phone">Teléfono Emergencia</Label>
                            <Input id="emergency_contact_phone" v-model="form.emergency_contact_phone" />
                        </div>
                        <div class="md:col-span-2">
                            <Label for="allergies">Alergias</Label>
                            <Textarea id="allergies" v-model="form.allergies" rows="2" />
                        </div>
                        <div class="md:col-span-2">
                            <Label for="medical_notes">Notas Médicas</Label>
                            <Textarea id="medical_notes" v-model="form.medical_notes" rows="2" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Notas</CardTitle>
                        <CardDescription>Observaciones</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Textarea id="notes" v-model="form.notes" rows="3" />
                    </CardContent>
                </Card>
            </form>
        </div>

        <div class="lg:col-span-1">
            <Card class="sticky top-4">
                <CardHeader>
                    <CardTitle>{{ displayName }}</CardTitle>
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
                                <Clock class="h-4 w-4 text-muted-foreground" />
                            </div>
                            <div class="flex-1 space-y-1">
                                <p class="font-medium">{{ activity.description }}</p>
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
                            Sin actividad
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

