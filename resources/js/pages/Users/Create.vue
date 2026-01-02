<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, router } from '@inertiajs/vue3';
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
    { title: 'Usuarios', href: '/users' },
    { title: 'Nuevo Usuario', href: '/users/create' },
];

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    company_ids: [] as number[],
});

const submit = () => {
    form.post('/users', {
        onSuccess: () => router.visit('/users'),
    });
};
</script>

<template>
    <Head title="Nuevo Usuario" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="icon" @click="router.visit('/users')">
                        <ArrowLeft class="h-5 w-5" />
                    </Button>
                    <div>
                        <h1 class="text-3xl font-bold">Nuevo Usuario</h1>
                        <p class="text-muted-foreground">Crea un nuevo usuario del sistema</p>
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
                        <!-- Información del Usuario -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Información del Usuario</CardTitle>
                                <CardDescription>Datos principales del usuario</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <!-- Nombre -->
                                <div>
                                    <Label for="name">Nombre Completo *</Label>
                                    <Input
                                        id="name"
                                        v-model="form.name"
                                        placeholder="Juan Pérez"
                                        :class="{ 'border-red-500': form.errors.name }"
                                    />
                                    <p v-if="form.errors.name" class="text-sm text-red-500 mt-1">
                                        {{ form.errors.name }}
                                    </p>
                                </div>

                                <!-- Email -->
                                <div>
                                    <Label for="email">Email *</Label>
                                    <Input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        placeholder="juan@example.com"
                                        :class="{ 'border-red-500': form.errors.email }"
                                    />
                                    <p v-if="form.errors.email" class="text-sm text-red-500 mt-1">
                                        {{ form.errors.email }}
                                    </p>
                                </div>

                                <!-- Compañías (Multi-select con checkboxes) -->
                                <div>
                                    <Label>Compañías * (mínimo 1)</Label>
                                    <div class="space-y-2 mt-2 border rounded-md p-4 max-h-48 overflow-y-auto">
                                        <div 
                                            v-for="company in companies" 
                                            :key="company.id"
                                            class="flex items-center space-x-2"
                                        >
                                            <input
                                                :id="`company-${company.id}`"
                                                type="checkbox"
                                                :value="company.id"
                                                v-model="form.company_ids"
                                                class="h-4 w-4 rounded border-gray-300"
                                            />
                                            <label 
                                                :for="`company-${company.id}`"
                                                class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                                            >
                                                {{ company.trade_name }}
                                            </label>
                                        </div>
                                    </div>
                                    <p v-if="form.errors.company_ids" class="text-sm text-red-500 mt-1">
                                        {{ form.errors.company_ids }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Contraseña -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Contraseña</CardTitle>
                                <CardDescription>Establece la contraseña del usuario</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <!-- Password -->
                                <div>
                                    <Label for="password">Contraseña *</Label>
                                    <Input
                                        id="password"
                                        v-model="form.password"
                                        type="password"
                                        placeholder="Mínimo 8 caracteres"
                                        :class="{ 'border-red-500': form.errors.password }"
                                    />
                                    <p v-if="form.errors.password" class="text-sm text-red-500 mt-1">
                                        {{ form.errors.password }}
                                    </p>
                                </div>

                                <!-- Password Confirmation -->
                                <div>
                                    <Label for="password_confirmation">Confirmar Contraseña *</Label>
                                    <Input
                                        id="password_confirmation"
                                        v-model="form.password_confirmation"
                                        type="password"
                                        placeholder="Confirma la contraseña"
                                    />
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
                                <p class="text-muted-foreground">Nuevo usuario</p>
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
                                La contraseña debe tener al menos 8 caracteres.
                            </p>
                            <p class="text-muted-foreground">
                                Cada usuario debe estar asignado a una compañía.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
