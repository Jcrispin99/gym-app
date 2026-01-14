<script setup lang="ts">
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

import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface Company {
    id: number;
    trade_name: string;
}

interface Props {
    companies: Company[];
}

defineProps<Props>();

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
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Nuevo Cliente</h1>
                    <p class="text-muted-foreground">
                        Registra un nuevo cliente del gimnasio
                    </p>
                </div>
                <Button variant="outline" @click="router.visit('/customers')">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Volver
                </Button>
            </div>

            <!-- Odoo-style Layout: Left form + Right info-->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Form (Left - 2 columns) -->
                <div class="lg:col-span-2">
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Personal Info -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Información Personal</CardTitle>
                                <CardDescription>Datos del cliente</CardDescription>
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
                                                <SelectItem value="DNI">DNI</SelectItem>
                                                <SelectItem value="RUC">RUC</SelectItem>
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

                                <div class="grid grid-cols-2 gap-4">
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

                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <Label for="birth_date">Fecha Nacimiento</Label>
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
                                                <SelectValue placeholder="Seleccionar..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="M">Masculino</SelectItem>
                                                <SelectItem value="F">Femenino</SelectItem>
                                                <SelectItem value="Other">Otro</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                </div>


                                <div>
                                    <Label for="company_id">Compañía/Sucursal *</Label>
                                    <Select v-model="form.company_id">
                                        <SelectTrigger :class="{ 'border-red-500': form.errors.company_id }">
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
                                    <p v-if="form.errors.company_id" class="text-sm text-red-500 mt-1">
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

                        <!-- Emergency Contact -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Contacto de Emergencia</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label for="emergency_contact_name">Nombre</Label>
                                        <Input
                                            id="emergency_contact_name"
                                            v-model="form.emergency_contact_name"
                                        />
                                    </div>
                                    <div>
                                        <Label for="emergency_contact_phone">Teléfono</Label>
                                        <Input
                                            id="emergency_contact_phone"
                                            v-model="form.emergency_contact_phone"
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Actions -->
                        <div class="flex justify-end gap-2">
                            <Button type="button" variant="outline" @click="router.visit('/customers')">
                                Cancelar
                            </Button>
                            <Button type="submit" :disabled="form.processing">
                                <Save class="mr-2 h-4 w-4" />
                                Guardar
                            </Button>
                        </div>
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
                                <ul class="mt-2 space-y-1 text-muted-foreground">
                                    <li>• Documento</li>
                                    <li>• Nombres y Apellidos</li>
                                    <li>• Compañía</li>
                                </ul>
                            </div>
                            
                            <div>
                                <p class="font-medium">Información de Contacto</p>
                                <p class="mt-2 text-muted-foreground">
                                    Asegúrate de registrar un email y teléfono válidos para contactar al cliente.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
