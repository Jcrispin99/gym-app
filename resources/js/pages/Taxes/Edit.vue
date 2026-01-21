<script setup lang="ts">
import FormPageHeader from '@/components/FormPageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Head, useForm } from '@inertiajs/vue3';
import { Save } from 'lucide-vue-next';

interface Tax {
    id: number;
    name: string;
    description: string | null;
    invoice_label: string | null;
    tax_type: string;
    affectation_type_code: string | null;
    rate_percent: number;
    is_price_inclusive: boolean;
    is_active: boolean;
    is_default: boolean;
    created_at: string;
}

interface Props {
    tax: Tax;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Impuestos', href: '/taxes' },
    { title: 'Editar', href: `/taxes/${props.tax.id}/edit` },
];

const form = useForm({
    name: props.tax.name,
    description: props.tax.description || '',
    invoice_label: props.tax.invoice_label || '',
    tax_type: props.tax.tax_type,
    affectation_type_code: props.tax.affectation_type_code || '',
    rate_percent: props.tax.rate_percent,
    is_price_inclusive: props.tax.is_price_inclusive,
    is_active: props.tax.is_active,
    is_default: props.tax.is_default,
});

const submit = () => {
    form.put(`/taxes/${props.tax.id}`);
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
        <Head :title="`Editar ${tax.name}`" />

        <div class="w-full p-4">
            <FormPageHeader
                title="Editar Impuesto"
                :description="tax.name"
                back-href="/taxes"
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
                                        :class="{
                                            'border-destructive':
                                                form.errors.name,
                                        }"
                                        required
                                    />
                                    <p
                                        v-if="form.errors.name"
                                        class="text-sm text-destructive"
                                    >
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
                                    <Label for="invoice_label"
                                        >Etiqueta en Factura</Label
                                    >
                                    <Input
                                        id="invoice_label"
                                        v-model="form.invoice_label"
                                        placeholder="Ej: IGV 18%"
                                    />
                                </div>

                                <!-- Tax Type -->
                                <div class="space-y-2">
                                    <Label for="tax_type"
                                        >Tipo de Impuesto *</Label
                                    >
                                    <Select v-model="form.tax_type">
                                        <SelectTrigger id="tax_type">
                                            <SelectValue
                                                placeholder="Seleccionar tipo"
                                            />
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
                                    <Label for="affectation_type_code"
                                        >Código de Afectación (SUNAT)</Label
                                    >
                                    <Input
                                        id="affectation_type_code"
                                        v-model="form.affectation_type_code"
                                        placeholder="Ej: 10, 20, 31, etc."
                                        maxlength="10"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Catálogo 07 de SUNAT: 10=Gravado,
                                        20=Exonerado, 31=Inafecto
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
                                            v-model:checked="
                                                form.is_price_inclusive
                                            "
                                        />
                                        <Label
                                            for="is_price_inclusive"
                                            class="cursor-pointer"
                                        >
                                            Precio incluye impuesto (TTC)
                                        </Label>
                                    </div>

                                    <div class="flex items-center space-x-2">
                                        <Checkbox
                                            id="is_active"
                                            v-model:checked="form.is_active"
                                        />
                                        <Label
                                            for="is_active"
                                            class="cursor-pointer"
                                        >
                                            Activo
                                        </Label>
                                    </div>

                                    <div class="flex items-center space-x-2">
                                        <Checkbox
                                            id="is_default"
                                            v-model:checked="form.is_default"
                                        />
                                        <Label
                                            for="is_default"
                                            class="cursor-pointer"
                                        >
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
                        <CardContent class="space-y-2 text-sm">
                            <div>
                                <p class="font-medium">Creado:</p>
                                <p class="text-muted-foreground">
                                    {{
                                        new Date(
                                            tax.created_at,
                                        ).toLocaleDateString()
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="font-medium">ID:</p>
                                <p class="text-muted-foreground">
                                    #{{ tax.id }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
