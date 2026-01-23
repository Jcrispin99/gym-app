<script setup lang="ts">
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
import AppLayout from '@/layouts/AppLayout.vue';

import FormPageHeader from '@/components/FormPageHeader.vue';
import PartnerLookupField from '@/components/PartnerLookupField.vue';
import { usePartnerLookup } from '@/composables/usePartnerLookup';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Save } from 'lucide-vue-next';

interface Company {
    id: number;
    trade_name: string;
}

interface Props {
    companies: Company[];
}

defineProps<Props>();

const { handlePartnerFound } = usePartnerLookup();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Clientes', href: '/customers' },
    { title: 'Nuevo Cliente', href: '/customers/create' },
];

const form = useForm({
    company_id: null as number | null,
    document_type: 'DNI' as string,
    document_number: '',
    first_name: '',
    last_name: '',
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
    photo_url: '',
});

const onPartnerFound = (data: any) => {
    handlePartnerFound(data, form);
};

const submit = () => {
    form.post('/customers', {
        onSuccess: () => router.visit('/customers'),
    });
};
</script>

<template>
    <Head title="Nuevo Cliente" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <FormPageHeader
                title="Nuevo Cliente"
                description="Registra un nuevo cliente del gimnasio"
                back-href="/customers"
            >
                <template #actions>
                    <Button @click="submit" :disabled="form.processing">
                        <Save class="mr-2 h-4 w-4" />
                        {{ form.processing ? 'Guardando...' : 'Guardar' }}
                    </Button>
                </template>
            </FormPageHeader>

            <!-- Odoo-style Layout: Left form + Right info-->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Main Form (Left - 2 columns) -->
                <div class="lg:col-span-2">
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Personal Info -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Información Personal</CardTitle>
                                <CardDescription
                                    >Datos del cliente</CardDescription
                                >
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <PartnerLookupField
                                    v-model:document-type="form.document_type"
                                    v-model:document-number="
                                        form.document_number
                                    "
                                    :error="form.errors.document_number"
                                    @found="onPartnerFound"
                                />

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label for="first_name"
                                            >Nombres *</Label
                                        >
                                        <Input
                                            id="first_name"
                                            v-model="form.first_name"
                                            :class="{
                                                'border-red-500':
                                                    form.errors.first_name,
                                            }"
                                        />
                                        <p
                                            v-if="form.errors.first_name"
                                            class="mt-1 text-sm text-red-500"
                                        >
                                            {{ form.errors.first_name }}
                                        </p>
                                    </div>

                                    <div>
                                        <Label for="last_name"
                                            >Apellidos *</Label
                                        >
                                        <Input
                                            id="last_name"
                                            v-model="form.last_name"
                                            :class="{
                                                'border-red-500':
                                                    form.errors.last_name,
                                            }"
                                        />
                                        <p
                                            v-if="form.errors.last_name"
                                            class="mt-1 text-sm text-red-500"
                                        >
                                            {{ form.errors.last_name }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <Label for="birth_date"
                                            >Fecha Nacimiento</Label
                                        >
                                        <Input
                                            id="birth_date"
                                            type="date"
                                            v-model="form.birth_date"
                                        />
                                    </div>

                                    <div>
                                        <Label for="gender">Género</Label>
                                        <Select v-model="form.gender">
                                            <SelectTrigger>
                                                <SelectValue
                                                    placeholder="Seleccionar..."
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="M"
                                                    >Masculino</SelectItem
                                                >
                                                <SelectItem value="F"
                                                    >Femenino</SelectItem
                                                >
                                                <SelectItem value="Other"
                                                    >Otro</SelectItem
                                                >
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div>
                                    <Label for="company_id"
                                        >Compañía/Sucursal *</Label
                                    >
                                    <Select v-model="form.company_id">
                                        <SelectTrigger
                                            :class="{
                                                'border-red-500':
                                                    form.errors.company_id,
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
                                        v-if="form.errors.company_id"
                                        class="mt-1 text-sm text-red-500"
                                    >
                                        {{ form.errors.company_id }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Contact Info -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Contacto</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-3 gap-4">
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
                                        <Input
                                            id="phone"
                                            v-model="form.phone"
                                        />
                                    </div>
                                    <div>
                                        <Label for="mobile">Celular</Label>
                                        <Input
                                            id="mobile"
                                            v-model="form.mobile"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <Label for="address">Dirección</Label>
                                    <Input
                                        id="address"
                                        v-model="form.address"
                                    />
                                </div>

                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <Label for="district">Distrito</Label>
                                        <Input
                                            id="district"
                                            v-model="form.district"
                                        />
                                    </div>
                                    <div>
                                        <Label for="province">Provincia</Label>
                                        <Input
                                            id="province"
                                            v-model="form.province"
                                        />
                                    </div>
                                    <div>
                                        <Label for="department"
                                            >Departamento</Label
                                        >
                                        <Input
                                            id="department"
                                            v-model="form.department"
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Emergency Contact -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Contacto de Emergencia</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label for="emergency_contact_name"
                                            >Nombre</Label
                                        >
                                        <Input
                                            id="emergency_contact_name"
                                            v-model="
                                                form.emergency_contact_name
                                            "
                                        />
                                    </div>
                                    <div>
                                        <Label for="emergency_contact_phone"
                                            >Teléfono</Label
                                        >
                                        <Input
                                            id="emergency_contact_phone"
                                            v-model="
                                                form.emergency_contact_phone
                                            "
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </form>
                </div>

                <!-- Info Sidebar (Right - 1 column) -->
                <div class="lg:col-span-1">
                    <Card class="sticky top-4">
                        <CardHeader>
                            <CardTitle>Información</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4 text-sm">
                            <div>
                                <p class="font-medium">Campos Requeridos</p>
                                <ul
                                    class="mt-2 space-y-1 text-muted-foreground"
                                >
                                    <li>• Documento</li>
                                    <li>• Nombres y Apellidos</li>
                                    <li>• Compañía</li>
                                </ul>
                            </div>

                            <div>
                                <p class="font-medium">
                                    Información de Contacto
                                </p>
                                <p class="mt-2 text-muted-foreground">
                                    Asegúrate de registrar un email y teléfono
                                    válidos para contactar al cliente.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
