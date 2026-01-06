<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Save } from 'lucide-vue-next';

interface Company {
    id: number;
    name: string;
}

interface Warehouse {
    id: number;
    name: string;
    location: string | null;
    company_id: number;
    company: Company;
    created_at: string;
    updated_at: string;
}

interface Props {
    warehouse: Warehouse;
    companies: Company[];
}

const props = defineProps<Props>();

const form = useForm({
    name: props.warehouse.name,
    location: props.warehouse.location || '',
    company_id: props.warehouse.company_id,
});

const handleCompanyChange = (value: any) => {
    form.company_id = value ? parseInt(value as string) : props.warehouse.company_id;
};

const submit = () => {
    form.put(`/warehouses/${props.warehouse.id}`, {
        preserveScroll: true,
    });
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleString('es-PE');
};
</script>

<template>
    <Head :title="`Editar - ${warehouse.name}`" />

    <AppLayout>
        <div class="p-4">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="icon" @click="router.visit('/warehouses')">
                        <ArrowLeft class="h-5 w-5" />
                    </Button>
                    <div>
                        <h1 class="text-3xl font-bold">{{ warehouse.name }}</h1>
                        <p class="text-muted-foreground">{{ warehouse.company.name }}</p>
                    </div>
                </div>
                <Button @click="submit" :disabled="form.processing">
                    <Save class="mr-2 h-4 w-4" />
                    {{ form.processing ? 'Guardando...' : 'Guardar Cambios' }}
                </Button>
            </div>

            <!-- Odoo-style Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Form (Left) -->
                <div class="lg:col-span-2">
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Información del Almacén -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Información del Almacén</CardTitle>
                                <CardDescription>Actualiza los datos del almacén</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <!-- Name -->
                                <div>
                                    <Label for="name">Código del Almacén *</Label>
                                    <Input
                                        id="name"
                                        v-model="form.name"
                                        :class="{ 'border-red-500': form.errors.name }"
                                    />
                                    <p v-if="form.errors.name" class="text-sm text-red-500 mt-1">
                                        {{ form.errors.name }}
                                    </p>
                                </div>

                                <!-- Company -->
                                <div>
                                    <Label for="company_id">Empresa *</Label>
                                    <Select
                                        :model-value="form.company_id?.toString()"
                                        @update:model-value="handleCompanyChange"
                                    >
                                        <SelectTrigger :class="{ 'border-red-500': form.errors.company_id }">
                                            <SelectValue placeholder="Seleccionar empresa..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="company in companies"
                                                :key="company.id"
                                                :value="company.id.toString()"
                                            >
                                                {{ company.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p v-if="form.errors.company_id" class="text-sm text-red-500 mt-1">
                                        {{ form.errors.company_id }}
                                    </p>
                                </div>

                                <!-- Location -->
                                <div>
                                    <Label for="location">Ubicación</Label>
                                    <Textarea
                                        id="location"
                                        v-model="form.location"
                                        placeholder="Dirección completa del almacén"
                                        :class="{ 'border-red-500': form.errors.location }"
                                        rows="3"
                                    />
                                    <p v-if="form.errors.location" class="text-sm text-red-500 mt-1">
                                        {{ form.errors.location }}
                                    </p>
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
                                <Badge variant="default">Activo</Badge>
                            </div>
                            <div>
                                <p class="font-medium">Empresa</p>
                                <p class="text-muted-foreground">{{ warehouse.company.name }}</p>
                            </div>
                            <div>
                                <p class="font-medium">Creado</p>
                                <p class="text-muted-foreground">{{ formatDate(warehouse.created_at) }}</p>
                            </div>
                            <div>
                                <p class="font-medium">Último cambio</p>
                                <p class="text-muted-foreground">{{ formatDate(warehouse.updated_at) }}</p>
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
                                El código debe ser único y fácil de identificar.
                            </p>
                            <p class="text-muted-foreground">
                                La ubicación ayuda a localizar físicamente el almacén.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
