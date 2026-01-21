<script setup lang="ts">
import FormPageHeader from '@/components/FormPageHeader.vue';
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
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Clock, Save, User as UserIcon } from 'lucide-vue-next';

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
    company_id: number;
    user_id: number | null;
    document_type: string;
    document_number: string;
    first_name: string;
    last_name: string;
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
    status: string;
    created_at: string;
    updated_at: string;
}

interface Props {
    customer: Customer;
    activities?: Activity[];
}

const props = withDefaults(defineProps<Props>(), {
    activities: () => [],
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Clientes', href: '/customers' },
    {
        title: `${props.customer.first_name} ${props.customer.last_name}`,
        href: `/customers/${props.customer.id}/edit`,
    },
];

const form = useForm({
    company_id: props.customer.company_id,
    document_type: props.customer.document_type,
    document_number: props.customer.document_number,
    first_name: props.customer.first_name,
    last_name: props.customer.last_name,
    email: props.customer.email || '',
    phone: props.customer.phone || '',
    mobile: props.customer.mobile || '',
    address: props.customer.address || '',
    district: props.customer.district || '',
    province: props.customer.province || '',
    department: props.customer.department || '',
    birth_date: props.customer.birth_date || '',
    gender: props.customer.gender || '',
    emergency_contact_name: props.customer.emergency_contact_name || '',
    emergency_contact_phone: props.customer.emergency_contact_phone || '',
    status: props.customer.status,
});

const submit = () => {
    form.put(`/customers/${props.customer.id}`);
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
</script>

<template>
    <Head :title="`Editar: ${customer.first_name} ${customer.last_name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <FormPageHeader
                title="Editar Cliente"
                :description="`${customer.first_name} ${customer.last_name}`"
                back-href="/customers"
            >
                <template #actions>
                    <Button @click="submit" :disabled="form.processing">
                        <Save class="mr-2 h-4 w-4" />
                        {{
                            form.processing ? 'Guardando...' : 'Guardar Cambios'
                        }}
                    </Button>
                </template>
            </FormPageHeader>

            <!-- Layout -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Main Form (Left) -->
                <div class="lg:col-span-2">
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Personal Info -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Información Personal</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label for="document_type"
                                            >Tipo Documento *</Label
                                        >
                                        <Select v-model="form.document_type">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="DNI"
                                                    >DNI</SelectItem
                                                >
                                                <SelectItem value="RUC"
                                                    >RUC</SelectItem
                                                >
                                                <SelectItem value="CE"
                                                    >Carnet
                                                    Extranjería</SelectItem
                                                >
                                                <SelectItem value="Passport"
                                                    >Pasaporte</SelectItem
                                                >
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div>
                                        <Label for="document_number"
                                            >Número Documento *</Label
                                        >
                                        <Input
                                            id="document_number"
                                            v-model="form.document_number"
                                            :class="{
                                                'border-red-500':
                                                    form.errors.document_number,
                                            }"
                                        />
                                        <p
                                            v-if="form.errors.document_number"
                                            class="mt-1 text-sm text-red-500"
                                        >
                                            {{ form.errors.document_number }}
                                        </p>
                                    </div>
                                </div>

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

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label for="status">Estado *</Label>
                                        <Select v-model="form.status">
                                            <SelectTrigger>
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
                                            </SelectContent>
                                        </Select>
                                    </div>
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

                <!-- Activity Log Sidebar (Right) -->
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
                                            {{ activity.description }}
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
        </div>
    </AppLayout>
</template>
