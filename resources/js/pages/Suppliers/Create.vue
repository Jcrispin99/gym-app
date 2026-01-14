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
import { Textarea } from '@/components/ui/textarea';
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
    { title: 'Proveedores', href: '/suppliers' },
    { title: 'Nuevo Proveedor', href: '/suppliers/create' },
];

const form = useForm({
    company_id: null as number | null,
    document_type: 'RUC' as string, // Default to RUC for suppliers typically
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
    payment_terms: '',
    supplier_category: '',
    notes: '',
});

const submit = () => {
    form.post('/suppliers', {
        onSuccess: () => router.visit('/suppliers'),
    });
};
</script>

<template>
    <Head title="Nuevo Proveedor" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Nuevo Proveedor</h1>
                    <p class="text-muted-foreground">
                        Registra un nuevo proveedor
                    </p>
                </div>
                <Button variant="outline" @click="router.visit('/suppliers')">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Volver
                </Button>
            </div>

            <!-- Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Form (Left - 2 columns) -->
                <div class="lg:col-span-2">
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Supplier Info -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Información del Proveedor</CardTitle>
                                <CardDescription>Datos principales</CardDescription>
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

                                <!-- Business Name (Only if RUC usually, but we'll show it or make it responsive) -->
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

                                <!-- Names (For non-RUC or contact person) -->
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
                                Guardar
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Info Sidebar (Right - 1 column) -->
                <div class="lg:col-span-1">
                    <Card class="sticky top-4">
                        <CardHeader>
                            <CardTitle>Ayuda</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4 text-sm">
                            <div>
                                <p class="font-medium">Registro de Proveedor</p>
                                <p class="mt-2 text-muted-foreground">
                                    Asegúrese de ingresar el RUC o documento correcto para la facturación.
                                </p>
                            </div>
                            
                            <div>
                                <p class="font-medium">Condiciones</p>
                                <ul class="mt-2 space-y-1 text-muted-foreground">
                                    <li>• Razón Social es requerida para RUC.</li>
                                    <li>• Nombres/Apellidos para personas naturales.</li>
                                </ul>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
