<script setup lang="ts">
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save, Clock, User as UserIcon } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

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
    company_id: number;
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
    payment_terms: string | null;
    supplier_category: string | null;
    notes: string | null;
    status: string;
    created_at: string;
    updated_at: string;
}

interface Props {
    supplier: Supplier;
    activities?: Activity[]; // Optional if not yet implemented in controller widely
}

const props = defineProps<Props>();

const getDisplayName = () => {
    return props.supplier.business_name || `${props.supplier.first_name} ${props.supplier.last_name}`;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Proveedores', href: '/suppliers' },
    { title: getDisplayName(), href: `/suppliers/${props.supplier.id}/edit` },
];

const form = useForm({
    company_id: props.supplier.company_id,
    document_type: props.supplier.document_type,
    document_number: props.supplier.document_number,
    business_name: props.supplier.business_name || '',
    first_name: props.supplier.first_name || '',
    last_name: props.supplier.last_name || '',
    email: props.supplier.email || '',
    phone: props.supplier.phone || '',
    mobile: props.supplier.mobile || '',
    address: props.supplier.address || '',
    district: props.supplier.district || '',
    province: props.supplier.province || '',
    department: props.supplier.department || '',
    payment_terms: props.supplier.payment_terms || '',
    supplier_category: props.supplier.supplier_category || '',
    notes: props.supplier.notes || '',
    status: props.supplier.status,
});

const submit = () => {
    form.put(`/suppliers/${props.supplier.id}`);
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
    <Head :title="`Editar: ${getDisplayName()}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">
                        Editar Proveedor
                    </h1>
                    <p class="text-muted-foreground">
                        {{ getDisplayName() }}
                    </p>
                </div>
                <Button variant="outline" @click="router.visit('/suppliers')">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Volver
                </Button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Form (Left - 2 columns) -->
                <div class="lg:col-span-2">
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Supplier Info -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Información del Proveedor</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label for="document_type">Tipo Documento *</Label>
                                        <Select v-model="form.document_type">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="RUC">RUC</SelectItem>
                                                <SelectItem value="DNI">DNI</SelectItem>
                                                <SelectItem value="CE">Carnet Extranjería</SelectItem>
                                                <SelectItem value="Passport">Pasaporte</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    
                                    <div>
                                        <Label for="document_number">Número Documento *</Label>
                                        <Input
                                            id="document_number"
                                            v-model="form.document_number"
                                            :class="{ 'border-red-500': form.errors.document_number }"
                                        />
                                        <p v-if="form.errors.document_number" class="text-sm text-red-500 mt-1">
                                            {{ form.errors.document_number }}
                                        </p>
                                    </div>
                                </div>

                                <div v-if="form.document_type === 'RUC'">
                                    <Label for="business_name">Razón Social *</Label>
                                    <Input
                                        id="business_name"
                                        v-model="form.business_name"
                                        :class="{ 'border-red-500': form.errors.business_name }"
                                    />
                                    <p v-if="form.errors.business_name" class="text-sm text-red-500 mt-1">
                                        {{ form.errors.business_name }}
                                    </p>
                                </div>

                                <div v-if="form.document_type !== 'RUC'" class="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label for="first_name">Nombres *</Label>
                                        <Input
                                            id="first_name"
                                            v-model="form.first_name"
                                            :class="{ 'border-red-500': form.errors.first_name }"
                                        />
                                        <p v-if="form.errors.first_name" class="text-sm text-red-500 mt-1">
                                            {{ form.errors.first_name }}
                                        </p>
                                    </div>

                                    <div>
                                        <Label for="last_name">Apellidos *</Label>
                                        <Input
                                            id="last_name"
                                            v-model="form.last_name"
                                            :class="{ 'border-red-500': form.errors.last_name }"
                                        />
                                        <p v-if="form.errors.last_name" class="text-sm text-red-500 mt-1">
                                            {{ form.errors.last_name }}
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <Label for="status">Estado *</Label>
                                    <Select v-model="form.status">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="active">Activo</SelectItem>
                                            <SelectItem value="inactive">Inactivo</SelectItem>
                                            <SelectItem value="suspended">Suspendido</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Contact Info -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Contacto y Dirección</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-3 gap-4">
                                    <div class="col-span-1">
                                        <Label for="email">Email</Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            v-model="form.email"
                                        />
                                    </div>
                                    <div class="col-span-1">
                                        <Label for="phone">Teléfono</Label>
                                        <Input id="phone" v-model="form.phone" />
                                    </div>
                                    <div class="col-span-1">
                                        <Label for="mobile">Celular</Label>
                                        <Input id="mobile" v-model="form.mobile" />
                                    </div>
                                </div>

                                <div>
                                    <Label for="address">Dirección</Label>
                                    <Input id="address" v-model="form.address" />
                                </div>

                                <div class="grid grid-cols-3 gap-4">
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
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Extra Info -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Información Adicional</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label for="payment_terms">Condiciones de Pago</Label>
                                        <Input 
                                            id="payment_terms" 
                                            v-model="form.payment_terms" 
                                            placeholder="Ej: 30 días, Contado"
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

                        <!-- Actions -->
                        <div class="flex justify-end gap-2">
                            <Button type="button" variant="outline" @click="router.visit('/suppliers')">
                                Cancelar
                            </Button>
                            <Button type="submit" :disabled="form.processing">
                                <Save class="mr-2 h-4 w-4" />
                                Guardar Cambios
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Info Sidebar (Right - 1 column) -->
                <div class="lg:col-span-1">
                    <!-- Activity Log (placeholder wrapper if empty) -->
                    <Card v-if="activities && activities.length > 0" class="sticky top-4">
                        <CardHeader>
                            <CardTitle>Historial de Cambios</CardTitle>
                            <CardDescription>Últimas actividades</CardDescription>
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
                                        <p v-if="activity.causer" class="text-xs text-muted-foreground flex items-center gap-1">
                                            <UserIcon class="h-3 w-3" />
                                            {{ activity.causer.name }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card v-else class="sticky top-4">
                         <CardHeader>
                            <CardTitle>Información</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-sm text-muted-foreground">
                                Modifica los datos del proveedor. El historial de cambios aparecerá aquí cuando haya actividad.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
