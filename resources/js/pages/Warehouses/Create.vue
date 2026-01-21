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
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Save } from 'lucide-vue-next';

interface Company {
    id: number;
    name: string;
}

interface Props {
    companies: Company[];
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Almacenes', href: '/warehouses' },
    { title: 'Nuevo Almacén', href: '/warehouses/create' },
];

const form = useForm({
    name: '',
    location: '',
    company_id: null as number | null,
});

const handleCompanyChange = (value: any) => {
    form.company_id = value ? parseInt(value as string) : null;
};

const submit = () => {
    form.post('/warehouses', {
        onSuccess: () => router.visit('/warehouses'),
    });
};
</script>

<template>
    <Head title="Nuevo Almacén" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <FormPageHeader
                title="Nuevo Almacén"
                description="Crea un nuevo almacén para gestionar inventario"
                back-href="/warehouses"
            >
                <template #actions>
                    <Button @click="submit" :disabled="form.processing">
                        <Save class="mr-2 h-4 w-4" />
                        {{ form.processing ? 'Guardando...' : 'Guardar' }}
                    </Button>
                </template>
            </FormPageHeader>

            <!-- Odoo-style Layout -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Main Form (Left) -->
                <div class="lg:col-span-2">
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Información del Almacén -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Información del Almacén</CardTitle>
                                <CardDescription
                                    >Completa los datos básicos del
                                    almacén</CardDescription
                                >
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <!-- Name -->
                                <div>
                                    <Label for="name"
                                        >Código del Almacén *</Label
                                    >
                                    <Input
                                        id="name"
                                        v-model="form.name"
                                        placeholder="Ej: IK01, ALMACEN-01"
                                        :class="{
                                            'border-red-500': form.errors.name,
                                        }"
                                    />
                                    <p
                                        v-if="form.errors.name"
                                        class="mt-1 text-sm text-red-500"
                                    >
                                        {{ form.errors.name }}
                                    </p>
                                </div>

                                <!-- Company -->
                                <div>
                                    <Label for="company_id">Empresa *</Label>
                                    <Select
                                        :model-value="
                                            form.company_id?.toString()
                                        "
                                        @update:model-value="
                                            handleCompanyChange
                                        "
                                    >
                                        <SelectTrigger
                                            :class="{
                                                'border-red-500':
                                                    form.errors.company_id,
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
                                                {{ company.name }}
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

                                <!-- Location -->
                                <div>
                                    <Label for="location">Ubicación</Label>
                                    <Textarea
                                        id="location"
                                        v-model="form.location"
                                        placeholder="Dirección completa del almacén"
                                        :class="{
                                            'border-red-500':
                                                form.errors.location,
                                        }"
                                        rows="3"
                                    />
                                    <p
                                        v-if="form.errors.location"
                                        class="mt-1 text-sm text-red-500"
                                    >
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
                                <p class="text-muted-foreground">
                                    Nuevo registro
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Help Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Ayuda</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm">
                            <p class="text-muted-foreground">
                                Los campos marcados con * son obligatorios.
                            </p>
                            <p class="text-muted-foreground">
                                El código debe ser único y fácil de identificar.
                            </p>
                            <p class="text-muted-foreground">
                                La ubicación ayuda a localizar físicamente el
                                almacén.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
