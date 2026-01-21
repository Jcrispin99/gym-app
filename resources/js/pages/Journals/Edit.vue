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
import { Save } from 'lucide-vue-next';

interface Journal {
    id: number;
    name: string;
    code: string;
    type: string;
    is_fiscal: boolean;
    document_type_code: string | null;
    company_id: number | null;
    sequence: {
        id: number;
        sequence_size: number;
        next_number: number;
        step: number;
    };
    created_at: string;
}

interface Company {
    id: number;
    name: string;
}

interface Props {
    journal: Journal;
    companies: Company[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Diarios', href: '/journals' },
    { title: 'Editar', href: `/journals/${props.journal.id}/edit` },
];

const form = useForm({
    name: props.journal.name,
    code: props.journal.code,
    type: props.journal.type,
    is_fiscal: props.journal.is_fiscal,
    document_type_code: props.journal.document_type_code || '',
    company_id: props.journal.company_id,
    next_number: props.journal.sequence.next_number,
});

const submit = () => {
    form.put(`/journals/${props.journal.id}`);
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
        <Head :title="`Editar ${journal.name}`" />

        <div class="w-full p-4">
            <FormPageHeader
                title="Editar Diario"
                :description="journal.name"
                back-href="/journals"
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
                                    Ajusta el próximo número que se generará
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <!-- Sequence Info (Read-only) -->
                                <div class="space-y-2">
                                    <Label>Tamaño de Secuencia</Label>
                                    <p class="text-sm text-muted-foreground">
                                        {{ journal.sequence.sequence_size }}
                                        dígitos (no editable)
                                    </p>
                                </div>

                                <!-- Next Number (Editable) -->
                                <div class="space-y-2">
                                    <Label for="next_number"
                                        >Próximo Número *</Label
                                    >
                                    <Input
                                        id="next_number"
                                        v-model.number="form.next_number"
                                        type="number"
                                        min="1"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        ⚠️ Ten cuidado al modificar este valor
                                    </p>
                                </div>

                                <!-- Step Info -->
                                <div class="space-y-2">
                                    <Label>Incremento</Label>
                                    <p class="text-sm text-muted-foreground">
                                        {{ journal.sequence.step }} (no
                                        editable)
                                    </p>
                                </div>

                                <!-- Preview -->
                                <div class="rounded-md bg-muted p-4">
                                    <p class="mb-2 text-sm font-medium">
                                        Próximo número que se generará:
                                    </p>
                                    <p class="font-mono text-lg">
                                        {{ form.code }}-{{
                                            String(form.next_number).padStart(
                                                journal.sequence.sequence_size,
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
                            <div>
                                <p class="font-medium">Creado:</p>
                                <p class="text-muted-foreground">
                                    {{
                                        new Date(
                                            journal.created_at,
                                        ).toLocaleDateString()
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="font-medium">ID de Secuencia:</p>
                                <p class="text-muted-foreground">
                                    #{{ journal.sequence.id }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Ayuda</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm">
                            <p class="text-muted-foreground">
                                Solo se puede editar el "Próximo Número" de la
                                secuencia.
                            </p>
                            <p class="text-muted-foreground">
                                Para reiniciar la secuencia a 1, usa el botón en
                                la lista de diarios.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
