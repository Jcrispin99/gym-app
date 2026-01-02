<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
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
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Save } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface Props {
    main_office: {
        id: number;
        trade_name: string;
    } | null;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Compañías', href: '/companies' },
    { title: 'Nueva Sucursal', href: '/companies/create' },
];

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
    parent_id: props.main_office?.id || null,
    branch_code: '',
    is_main_office: false,
});

const submit = () => {
    form.post('/companies', {
        onSuccess: () => router.visit('/companies'),
    });
};
</script>

<template>
    <Head title="Nueva Sucursal" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="icon" @click="router.visit('/companies')">
                        <ArrowLeft class="h-5 w-5" />
                    </Button>
                    <div>
                        <h1 class="text-3xl font-bold">Nueva Sucursal</h1>
                        <p class="text-muted-foreground">Crea una nueva compañía o sucursal</p>
                    </div>
                </div>
                <Button @click="submit" :disabled="form.processing">
                    <Save class="mr-2 h-4 w-4" />
                    {{ form.processing ? 'Guardando...' : 'Guardar' }}
                </Button>
            </div>

            <!-- Odoo-style Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Form (Left) -->
                <div class="lg:col-span-2">
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Información General -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Información General</CardTitle>
                                <CardDescription>Datos principales de la compañía</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <!-- Razón Social -->
                                <div>
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
                                <div>
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

                                <!-- Es Sucursal? -->
                                <div>
                                    <Label for="parent_id">¿Es una sucursal?</Label>
                                    <Select v-model="form.parent_id">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Seleccionar..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="null">No - Es casa matriz</SelectItem>
                                            <SelectItem 
                                                v-if="main_office" 
                                                :value="main_office.id"
                                            >
                                                Sí - Sucursal de {{ main_office.trade_name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <!-- Código de Sucursal (only if es sucursal) -->
                                <div v-if="form.parent_id">
                                    <Label for="branch_code">Código de Sucursal *</Label>
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
                            </CardContent>
                        </Card>

                        <!-- Ubicación -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Ubicación</CardTitle>
                                <CardDescription>Dirección y ubicación geográfica</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div>
                                    <Label for="address">Dirección</Label>
                                    <Input id="address" v-model="form.address" placeholder="Av. Principal 123" />
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label for="department">Departamento</Label>
                                        <Input id="department" v-model="form.department" placeholder="Lima" />
                                    </div>
                                    <div>
                                        <Label for="province">Provincia</Label>
                                        <Input id="province" v-model="form.province" placeholder="Lima" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label for="district">Distrito</Label>
                                        <Input id="district" v-model="form.district" placeholder="San Isidro" />
                                    </div>
                                    <div>
                                        <Label for="ubigeo">Ubigeo</Label>
                                        <Input id="ubigeo" v-model="form.ubigeo" placeholder="150130" maxlength="6" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Contacto -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Contacto</CardTitle>
                                <CardDescription>Información de contacto</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div>
                                    <Label for="phone">Teléfono</Label>
                                    <Input id="phone" v-model="form.phone" placeholder="987654321" />
                                </div>
                                <div>
                                    <Label for="email">Email</Label>
                                    <Input id="email" v-model="form.email" type="email" placeholder="contacto@krakengym.com" />
                                </div>
                            </CardContent>
                        </Card>
                    </form>
                </div>

                <!-- Sidebar (Right) -->
                <div class="space-y-6">
                    <!-- Info Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Información</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3 text-sm">
                            <div>
                                <p class="font-medium">Estado</p>
                                <p class="text-muted-foreground">Nuevo registro</p>
                            </div>
                            <div>
                                <p class="font-medium">Tipo</p>
                                <p class="text-muted-foreground">
                                    {{ form.parent_id ? 'Sucursal' : 'Casa Matriz' }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Help Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Ayuda</CardTitle>
                        </CardHeader>
                        <CardContent class="text-sm space-y-2">
                            <p class="text-muted-foreground">
                                Los campos marcados con * son obligatorios.
                            </p>
                            <p class="text-muted-foreground">
                                Si es una sucursal, debe tener un código único.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
