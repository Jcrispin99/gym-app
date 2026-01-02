<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Company {
    id?: number;
    business_name: string;
    trade_name: string;
    ruc: string;
    address: string | null;
    phone: string | null;
    email: string | null;
    ubigeo: string | null;
    urbanization: string | null;
    department: string | null;
    province: string | null;
    district: string | null;
    parent_id: number | null;
    branch_code: string | null;
    is_main_office: boolean;
}

interface Props {
    open: boolean;
    company?: Company | null;
    mainOfficeId?: number;
}

const props = withDefaults(defineProps<Props>(), {
    company: null,
    mainOfficeId: undefined,
});

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const isEditing = computed(() => !!props.company?.id);

const form = useForm({
    business_name: '',
    trade_name: '',
    ruc: '',
    address: '',
    phone: '',
    email: '',
    ubigeo: '',
    urbanization: '',
    department: '',
    province: '',
    district: '',
    parent_id: props.mainOfficeId || null,
    branch_code: '',
    is_main_office: false,
});

// Watch for company changes (when editing)
watch(() => props.company, (company) => {
    if (company) {
        form.business_name = company.business_name || '';
        form.trade_name = company.trade_name || '';
        form.ruc = company.ruc || '';
        form.address = company.address || '';
        form.phone = company.phone || '';
        form.email = company.email || '';
        form.ubigeo = company.ubigeo || '';
        form.urbanization = company.urbanization || '';
        form.department = company.department || '';
        form.province = company.province || '';
        form.district = company.district || '';
        form.parent_id = company.parent_id;
        form.branch_code = company.branch_code || '';
        form.is_main_office = company.is_main_office;
    }
}, { immediate: true });

const submit = () => {
    if (isEditing.value) {
        form.put(`/companies/${props.company?.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                emit('update:open', false);
                form.reset();
            },
        });
    } else {
        form.post('/companies', {
            preserveScroll: true,
            onSuccess: () => {
                emit('update:open', false);
                form.reset();
            },
        });
    }
};

const closeDialog = () => {
    emit('update:open', false);
    form.reset();
    form.clearErrors();
};
</script>

<template>
    <Dialog :open="open" @update:open="closeDialog">
        <DialogContent class="sm:max-w-[600px]">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing ? 'Editar Compañía' : 'Nueva Sucursal' }}
                </DialogTitle>
                <DialogDescription>
                    {{ isEditing ? 'Actualiza los datos de la compañía' : 'Agrega una nueva sucursal' }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <!-- Razón Social -->
                    <div class="col-span-2">
                        <Label for="business_name">Razón Social *</Label>
                        <Input
                            id="business_name"
                            v-model="form.business_name"
                            placeholder="KRAKEN GYM S.A.C."
                            :class="{ 'border-red-500': form.errors.business_name }"
                        />
                        <p v-if="form.errors.business_name" class="text-sm text-red-500 mt-1">
                            {{ form.errors.business_name }}
                        </p>
                    </div>

                    <!-- Nombre Comercial -->
                    <div class="col-span-2">
                        <Label for="trade_name">Nombre Comercial *</Label>
                        <Input
                            id="trade_name"
                            v-model="form.trade_name"
                            placeholder="Kraken Gym - San Isidro"
                            :class="{ 'border-red-500': form.errors.trade_name }"
                        />
                        <p v-if="form.errors.trade_name" class="text-sm text-red-500 mt-1">
                            {{ form.errors.trade_name }}
                        </p>
                    </div>

                    <!-- RUC -->
                    <div>
                        <Label for="ruc">RUC *</Label>
                        <Input
                            id="ruc"
                            v-model="form.ruc"
                            placeholder="20123456789"
                            maxlength="11"
                            :class="{ 'border-red-500': form.errors.ruc }"
                        />
                        <p v-if="form.errors.ruc" class="text-sm text-red-500 mt-1">
                            {{ form.errors.ruc }}
                        </p>
                    </div>

                    <!-- Código de Sucursal -->
                    <div>
                        <Label for="branch_code">Código de Sucursal</Label>
                        <Input
                            id="branch_code"
                            v-model="form.branch_code"
                            placeholder="SUC-001"
                            :class="{ 'border-red-500': form.errors.branch_code }"
                        />
                        <p v-if="form.errors.branch_code" class="text-sm text-red-500 mt-1">
                            {{ form.errors.branch_code }}
                        </p>
                    </div>

                    <!-- Dirección -->
                    <div class="col-span-2">
                        <Label for="address">Dirección</Label>
                        <Input
                            id="address"
                            v-model="form.address"
                            placeholder="Av. Principal 123"
                        />
                    </div>

                    <!-- Departamento -->
                    <div>
                        <Label for="department">Departamento</Label>
                        <Input
                            id="department"
                            v-model="form.department"
                            placeholder="Lima"
                        />
                    </div>

                    <!-- Provincia -->
                    <div>
                        <Label for="province">Provincia</Label>
                        <Input
                            id="province"
                            v-model="form.province"
                            placeholder="Lima"
                        />
                    </div>

                    <!-- Distrito -->
                    <div>
                        <Label for="district">Distrito</Label>
                        <Input
                            id="district"
                            v-model="form.district"
                            placeholder="San Isidro"
                        />
                    </div>

                    <!-- Ubigeo -->
                    <div>
                        <Label for="ubigeo">Ubigeo</Label>
                        <Input
                            id="ubigeo"
                            v-model="form.ubigeo"
                            placeholder="150130"
                            maxlength="6"
                        />
                    </div>

                    <!-- Teléfono -->
                    <div>
                        <Label for="phone">Teléfono</Label>
                        <Input
                            id="phone"
                            v-model="form.phone"
                            placeholder="987654321"
                        />
                    </div>

                    <!-- Email -->
                    <div>
                        <Label for="email">Email</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            placeholder="contacto@krakengym.com"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="closeDialog">
                        Cancelar
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Guardando...' : (isEditing ? 'Actualizar' : 'Crear') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
