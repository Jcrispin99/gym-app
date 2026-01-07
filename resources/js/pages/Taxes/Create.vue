<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Impuestos', href: '/taxes' },
    { title: 'Crear', href: '/taxes/create' },
];

const form = useForm({
    name: '',
    description: '',
    invoice_label: '',
    tax_type: 'IGV',
    affectation_type_code: '',
    rate_percent: 0,
    is_price_inclusive: false,
    is_active: true,
    is_default: false,
});

const submit = () => {
    form.post('/taxes');
};

const taxTypes = [
    { value: 'IGV', label: 'IGV (Impuesto General a las Ventas)' },
    { value: 'ISC', label: 'ISC (Impuesto Selectivo al Consumo)' },
    { value: 'RETENCION', label: 'Retención' },
    { value: 'PERCEPCION', label: 'Percepción' },
    { value: 'OTRO', label: 'Otro' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Crear Impuesto" />

        <div class="container mx-auto p-4 max-w-4xl">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="icon" as-child>
                        <a href="/taxes">
                            <ArrowLeft class="h-5 w-5" />
                        </a>
                    </Button>
                    <div>
                        <h1 class="text-2xl font-bold">Crear Impuesto</h1>
                        <p class="text-sm text-muted-foreground">
                            Configura un nuevo tipo de impuesto
                        </p>
                    </div>
                </div>
                <Button @click="submit" :disabled="form.processing">
                    Guardar
                </Button>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Main Form -->
                <div class="lg:col-span-2">
                    <form @submit.prevent="submit" class="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Información del Impuesto</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <!-- Name -->
                                <div class="space-y-2">
                                    <Label for="name">Nombre *</Label>
                                    <Input
                                        id="name"
                                        v-model="form.name"
                                        placeholder="Ej: 18% IGV"
                                        :class="{ 'border-destructive': form.errors.name }"
                                        required
                                    />
                                    <p v-if="form.errors.name" class="text-sm text-destructive">
                                        {{ form.errors.name }}
                                    </p>
                                </div>

                                <!-- Description -->
                                <div class="space-y-2">
                                    <Label for="description">Descripción</Label>
                                    <Textarea
                                        id="description"
                                        v-model="form.description"
                                        placeholder="Descripción del impuesto"
                                        rows="2"
                                    />
                                </div>

                                <!-- Invoice Label -->
                                <div class="space-y-2">
                                    <Label for="invoice_label">Etiqueta en Factura</Label>
                                    <Input
                                        id="invoice_label"
                                        v-model="form.invoice_label"
                                        placeholder="Ej: IGV 18%"
                                    />
                                </div>

                                <!-- Tax Type -->
                                <div class="space-y-2">
                                    <Label for="tax_type">Tipo de Impuesto *</Label>
                                    <Select v-model="form.tax_type">
                                        <SelectTrigger id="tax_type">
                                            <SelectValue placeholder="Seleccionar tipo" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="type in taxTypes"
                                                :key="type.value"
                                                :value="type.value"
                                            >
                                                {{ type.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <!-- Affectation Type Code (SUNAT) -->
                                <div class="space-y-2">
                                    <Label for="affectation_type_code">Código de Afectación (SUNAT)</Label>
                                    <Input
                                        id="affectation_type_code"
                                        v-model="form.affectation_type_code"
                                        placeholder="Ej: 10, 20, 31, etc."
                                        maxlength="10"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Catálogo 07 de SUNAT: 10=Gravado, 20=Exonerado, 31=Inafecto
                                    </p>
                                </div>

                                <!-- Rate Percent -->
                                <div class="space-y-2">
                                    <Label for="rate_percent">Tasa (%) *</Label>
                                    <Input
                                        id="rate_percent"
                                        v-model.number="form.rate_percent"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        required
                                    />
                                </div>

                                <!-- Checkboxes -->
                                <div class="space-y-3">
                                    <div class="flex items-center space-x-2">
                                        <Checkbox
                                            id="is_price_inclusive"
                                            v-model:checked="form.is_price_inclusive"
                                        />
                                        <Label for="is_price_inclusive" class="cursor-pointer">
                                            Precio incluye impuesto (TTC)
                                        </Label>
                                    </div>

                                    <div class="flex items-center space-x-2">
                                        <Checkbox
                                            id="is_active"
                                            v-model:checked="form.is_active"
                                        />
                                        <Label for="is_active" class="cursor-pointer">
                                            Activo
                                        </Label>
                                    </div>

                                    <div class="flex items-center space-x-2">
                                        <Checkbox
                                            id="is_default"
                                            v-model:checked="form.is_default"
                                        />
                                        <Label for="is_default" class="cursor-pointer">
                                            Usar por defecto
                                        </Label>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </form>
                </div>

                <!-- Sidebar -->
                <div class="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Información</CardTitle>
                        </CardHeader>
                        <CardContent class="text-sm space-y-2">
                            <p class="text-muted-foreground">
                                Los impuestos se aplicarán automáticamente a los productos y servicios.
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Ayuda</CardTitle>
                        </CardHeader>
                        <CardContent class="text-sm space-y-2">
                            <p class="font-medium">Tipos comunes:</p>
                            <ul class="list-disc list-inside text-muted-foreground space-y-1">
                                <li>IGV 18%: Impuesto general</li>
                                <li>Exonerado 0%: Sin impuesto</li>
                                <li>ISC: Para productos específicos</li>
                            </ul>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
