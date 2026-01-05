<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, X } from 'lucide-vue-next';

interface AttributeValue {
    id: number;
    value: string;
}

interface Attribute {
    id: number;
    name: string;
    is_active: boolean;
    attribute_values: AttributeValue[];
}

interface Props {
    attribute: Attribute;
}

const props = defineProps<Props>();

const form = useForm({
    name: props.attribute.name,
    is_active: props.attribute.is_active,
    values: props.attribute.attribute_values.map((v) => v.value),
});

const addValue = () => {
    form.values.push('');
};

const removeValue = (index: number) => {
    if (form.values.length > 1) {
        form.values.splice(index, 1);
    }
};

const submit = () => {
    // Filter out empty values before submitting
    const filteredValues = form.values.filter((v) => v.trim() !== '');
    form.transform((data) => ({
        ...data,
        values: filteredValues,
    })).put(`/attributes/${props.attribute.id}`);
};
</script>

<template>
    <Head :title="`Editar ${attribute.name}`" />

    <AppLayout>
        <div class="flex flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="icon" @click="$inertia.visit('/attributes')">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Editar Atributo</h1>
                    <p class="text-muted-foreground">{{ attribute.name }}</p>
                </div>
            </div>

            <!-- Form -->
            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle>Información del Atributo</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Name -->
                        <div class="space-y-2">
                            <Label for="name">Nombre del Atributo *</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="Ej: Color, Talla, Material..."
                                :class="{ 'border-destructive': form.errors.name }"
                                required
                            />
                            <p v-if="form.errors.name" class="text-sm text-destructive">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <!-- Values -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <Label>Valores del Atributo *</Label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="addValue"
                                >
                                    <Plus class="mr-2 h-4 w-4" />
                                    Agregar Valor
                                </Button>
                            </div>

                            <div class="space-y-2">
                                <div
                                    v-for="(value, index) in form.values"
                                    :key="index"
                                    class="flex gap-2"
                                >
                                    <Input
                                        v-model="form.values[index]"
                                        :placeholder="`Valor ${index + 1}`"
                                        class="flex-1"
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        @click="removeValue(index)"
                                        :disabled="form.values.length === 1"
                                    >
                                        <X class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>

                            <p class="text-xs text-muted-foreground">
                                Agrega al menos un valor para el atributo. Ej: Rojo, Azul, Verde
                            </p>
                            <p v-if="form.errors.values" class="text-sm text-destructive">
                                {{ form.errors.values }}
                            </p>
                        </div>

                        <!-- Is Active -->
                        <div class="flex items-center justify-between rounded-lg border p-4">
                            <div class="space-y-0.5">
                                <Label for="is_active">Estado Activo</Label>
                                <p class="text-sm text-muted-foreground">
                                    El atributo estará disponible para asignar a productos
                                </p>
                            </div>
                            <Switch id="is_active" v-model:checked="form.is_active" />
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-2">
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Guardando...' : 'Actualizar Atributo' }}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                @click="$inertia.visit('/attributes')"
                            >
                                Cancelar
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
