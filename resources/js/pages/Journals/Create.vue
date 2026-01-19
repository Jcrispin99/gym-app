<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

interface Company {
    id: number;
    name: string;
}

interface Props {
    companies: Company[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Diarios', href: '/journals' },
    { title: 'Crear', href: '/journals/create' },
];

const form = useForm({
    name: '',
    code: '',
    type: 'purchase',
    is_fiscal: false,
    document_type_code: '' as string | null,
    company_id: null as number | null,
    sequence_size: 8,
    step: 1,
    next_number: 1,
});

const submit = () => {
    form.post('/journals');
};

const journalTypes = [
    { value: 'sale', label: 'Venta' },
    { value: 'purchase', label: 'Compra' },
    { value: 'purchase-order', label: 'Orden de Compra' },
    { value: 'quote', label: 'Cotización' },
    { value: 'cash', label: 'Caja' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Crear Diario" />

        <div class="w-full p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="icon" as-child>
                        <a href="/journals">
                            <ArrowLeft class="h-5 w-5" />
                        </a>
                    </Button>
                    <div>
                        <h1 class="text-2xl font-bold">Crear Diario</h1>
                        <p class="text-sm text-muted-foreground">
                            Configura un nuevo diario contable con su numeración
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
                                <CardTitle>Información del Diario</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <!-- Name -->
                                <div class="space-y-2">
                                    <Label for="name">Nombre *</Label>
                                    <Input
                                        id="name"
                                        v-model="form.name"
                                        placeholder="Ej: Compras Locales"
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

                                <!-- Code -->
                                <div class="space-y-2">
                                    <Label for="code">Código (Serie) *</Label>
                                    <Input
                                        id="code"
                                        v-model="form.code"
                                        placeholder="Ej: COMP"
                                        :class="{
                                            'border-destructive':
                                                form.errors.code,
                                        }"
                                        required
                                        maxlength="10"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Será usado como serie en los documentos
                                        (ej: COMP-00000001)
                                    </p>
                                    <p
                                        v-if="form.errors.code"
                                        class="text-sm text-destructive"
                                    >
                                        {{ form.errors.code }}
                                    </p>
                                </div>

                                <!-- Type -->
                                <div class="space-y-2">
                                    <Label for="type">Tipo de Diario *</Label>
                                    <Select v-model="form.type">
                                        <SelectTrigger id="type">
                                            <SelectValue
                                                placeholder="Seleccionar tipo"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="type in journalTypes"
                                                :key="type.value"
                                                :value="type.value"
                                            >
                                                {{ type.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <!-- Is Fiscal -->
                                <div class="flex items-center space-x-2">
                                    <Checkbox
                                        id="is_fiscal"
                                        v-model:checked="form.is_fiscal"
                                    />
                                    <Label
                                        for="is_fiscal"
                                        class="cursor-pointer"
                                    >
                                        Es documento fiscal (Factura/Boleta)
                                    </Label>
                                </div>

                                <!-- Document Type Code (SUNAT) -->
                                <div v-if="form.is_fiscal" class="space-y-2">
                                    <Label for="document_type_code"
                                        >Código de Tipo de Documento
                                        (SUNAT)</Label
                                    >
                                    <Input
                                        id="document_type_code"
                                        v-model="form.document_type_code"
                                        placeholder="Ej: 01 (Factura), 03 (Boleta)"
                                        maxlength="2"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        01: Factura, 03: Boleta, 07: Nota
                                        Crédito, 08: Nota Débito
                                    </p>
                                </div>

                                <!-- Company -->
                                <div class="space-y-2">
                                    <Label for="company_id">Empresa</Label>
                                    <Select v-model="form.company_id">
                                        <SelectTrigger id="company_id">
                                            <SelectValue
                                                placeholder="Seleccionar empresa"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="company in companies"
                                                :key="company.id"
                                                :value="company.id"
                                            >
                                                {{ company.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Sequence Configuration -->
                        <Card>
                            <CardHeader>
                                <CardTitle
                                    >Configuración de Numeración</CardTitle
                                >
                                <CardDescription>
                                    Define cómo se generarán los números
                                    correlativos
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <!-- Sequence Size -->
                                <div class="space-y-2">
                                    <Label for="sequence_size"
                                        >Tamaño de Secuencia</Label
                                    >
                                    <Input
                                        id="sequence_size"
                                        v-model.number="form.sequence_size"
                                        type="number"
                                        min="4"
                                        max="12"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Número de dígitos (ej: 8 = 00000001)
                                    </p>
                                </div>

                                <!-- Next Number -->
                                <div class="space-y-2">
                                    <Label for="next_number"
                                        >Próximo Número</Label
                                    >
                                    <Input
                                        id="next_number"
                                        v-model.number="form.next_number"
                                        type="number"
                                        min="1"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Número inicial (normalmente 1)
                                    </p>
                                </div>

                                <!-- Step -->
                                <div class="space-y-2">
                                    <Label for="step">Incremento</Label>
                                    <Input
                                        id="step"
                                        v-model.number="form.step"
                                        type="number"
                                        min="1"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        De cuánto en cuánto incrementar
                                        (normalmente 1)
                                    </p>
                                </div>

                                <!-- Preview -->
                                <div class="rounded-md bg-muted p-4">
                                    <p class="mb-2 text-sm font-medium">
                                        Vista previa:
                                    </p>
                                    <p class="font-mono text-lg">
                                        {{ form.code || 'XXXX' }}-{{
                                            String(form.next_number).padStart(
                                                form.sequence_size,
                                                '0',
                                            )
                                        }}
                                    </p>
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
                            <p class="text-muted-foreground">
                                Los diarios se usan para generar numeración
                                automática de documentos.
                            </p>
                            <p class="text-muted-foreground">
                                Cada diario tiene su propia secuencia
                                independiente.
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Ayuda</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm">
                            <p class="font-medium">Tipos comunes:</p>
                            <ul
                                class="list-inside list-disc space-y-1 text-muted-foreground"
                            >
                                <li>Venta: Para facturas y boletas</li>
                                <li>Compra: Para registrar compras</li>
                                <li>Orden de Compra: Para OC</li>
                            </ul>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
