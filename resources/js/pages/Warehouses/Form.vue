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
import { Textarea } from '@/components/ui/textarea';
import axios from 'axios';
import { onMounted, ref } from 'vue';

interface Company {
    id: number;
    trade_name?: string | null;
    business_name?: string | null;
    district?: string | null;
}

interface Warehouse {
    id: number;
    name: string;
    location: string | null;
    company_id: number;
    created_at: string;
    updated_at: string;
    company?: Company;
}

type Mode = 'create' | 'edit';

const props = defineProps<{
    mode: Mode;
    warehouseId?: number | null;
}>();

const emit = defineEmits<{
    (e: 'loaded', warehouse: Warehouse): void;
    (e: 'saved', warehouse: Warehouse): void;
}>();

const companies = ref<Company[]>([]);
const warehouse = ref<Warehouse | null>(null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const form = ref({
    name: '',
    location: '',
    company_id: null as number | null,
});

const companyLabel = (company: Company) => {
    return (
        company.trade_name || company.business_name || `Empresa ${company.id}`
    );
};

const loadCompanies = async () => {
    try {
        const response = await axios.get('/api/companies', {
            headers: { Accept: 'application/json' },
        });

        const main = response.data?.main_office
            ? [response.data.main_office]
            : [];
        const branches = response.data?.branches || [];
        companies.value = [...main, ...branches] as Company[];
    } catch (e) {
        console.error('Error loading companies:', e);
        companies.value = [];
    }
};

const loadWarehouse = async () => {
    if (props.mode !== 'edit' || !props.warehouseId) return;

    try {
        const response = await axios.get(
            `/api/warehouses/${props.warehouseId}`,
            {
                headers: { Accept: 'application/json' },
            },
        );
        warehouse.value = response.data?.data as Warehouse;
        if (warehouse.value) {
            form.value = {
                name: warehouse.value.name,
                location: warehouse.value.location || '',
                company_id: warehouse.value.company_id,
            };
            emit('loaded', warehouse.value);
        }
    } catch (e) {
        console.error('Error loading warehouse:', e);
        warehouse.value = null;
    }
};

const handleCompanyChange = (value: any) => {
    form.value.company_id = value ? parseInt(value as string) : null;
};

const submit = async () => {
    processing.value = true;
    errors.value = {};

    try {
        const headers = { Accept: 'application/json' };

        if (props.mode === 'create') {
            const response = await axios.post('/api/warehouses', form.value, {
                headers,
            });
            const saved = response.data?.data as Warehouse;
            emit('saved', saved);
            return;
        }

        if (!props.warehouseId) return;

        const response = await axios.put(
            `/api/warehouses/${props.warehouseId}`,
            form.value,
            { headers },
        );
        const saved = response.data?.data as Warehouse;
        warehouse.value = saved;
        emit('saved', saved);
    } catch (e: any) {
        if (e?.response?.status === 422) {
            errors.value = e.response.data?.errors || {};
        } else {
            console.error('Error saving warehouse:', e);
        }
    } finally {
        processing.value = false;
    }
};

defineExpose({
    submit,
    processing,
});

onMounted(async () => {
    await Promise.all([loadCompanies(), loadWarehouse()]);
});
</script>

<template>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <form @submit.prevent="submit" class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Información del Almacén</CardTitle>
                        <CardDescription>
                            {{
                                mode === 'create'
                                    ? 'Completa los datos básicos del almacén'
                                    : 'Actualiza los datos del almacén'
                            }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div>
                            <Label for="name">Código del Almacén *</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="Ej: IK01, ALMACEN-01"
                                :class="{
                                    'border-red-500': errors.name,
                                }"
                            />
                            <p
                                v-if="errors.name"
                                class="mt-1 text-sm text-red-500"
                            >
                                {{ errors.name }}
                            </p>
                        </div>

                        <div>
                            <Label for="company_id">Empresa *</Label>
                            <Select
                                :model-value="form.company_id?.toString()"
                                @update:model-value="handleCompanyChange"
                            >
                                <SelectTrigger
                                    :class="{
                                        'border-red-500': errors.company_id,
                                    }"
                                >
                                    <SelectValue
                                        placeholder="Seleccionar empresa..."
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="company in companies"
                                        :key="company.id"
                                        :value="company.id.toString()"
                                    >
                                        {{ companyLabel(company) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p
                                v-if="errors.company_id"
                                class="mt-1 text-sm text-red-500"
                            >
                                {{ errors.company_id }}
                            </p>
                        </div>

                        <div>
                            <Label for="location">Ubicación</Label>
                            <Textarea
                                id="location"
                                v-model="form.location"
                                placeholder="Dirección completa del almacén"
                                :class="{
                                    'border-red-500': errors.location,
                                }"
                                rows="3"
                            />
                            <p
                                v-if="errors.location"
                                class="mt-1 text-sm text-red-500"
                            >
                                {{ errors.location }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </div>

        <div class="space-y-6">
            <slot
                name="sidebar"
                :mode="mode"
                :warehouse="warehouse"
                :processing="processing"
                :submit="submit"
            />
        </div>
    </div>
</template>
