<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import axios from 'axios';
import { Plus, X } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

interface AttributeValue {
    id: number;
    value: string;
}

interface Attribute {
    id: number;
    name: string;
    is_active: boolean;
    attribute_values: AttributeValue[];
    created_at?: string;
    updated_at?: string;
}

type Mode = 'create' | 'edit';

const props = defineProps<{
    mode: Mode;
    attributeId?: number | null;
    initialName?: string;
}>();

const emit = defineEmits<{
    (e: 'loaded', attribute: Attribute): void;
    (e: 'saved', attribute: Attribute): void;
}>();

const attribute = ref<Attribute | null>(null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const form = ref({
    name: '',
    is_active: true,
    values: [''] as string[],
});

const addValue = () => {
    form.value.values.push('');
};

const removeValue = (index: number) => {
    if (form.value.values.length > 1) {
        form.value.values.splice(index, 1);
    }
};

const loadAttribute = async () => {
    if (props.mode !== 'edit' || !props.attributeId) return;

    try {
        const response = await axios.get(
            `/api/attributes/${props.attributeId}`,
            {
                headers: { Accept: 'application/json' },
            },
        );
        attribute.value = response.data?.data as Attribute;
        if (attribute.value) {
            form.value = {
                name: attribute.value.name,
                is_active: !!attribute.value.is_active,
                values: attribute.value.attribute_values?.map(
                    (v) => v.value,
                ) || [''],
            };
            if (form.value.values.length === 0) {
                form.value.values = [''];
            }
            emit('loaded', attribute.value);
        }
    } catch (e) {
        console.error('Error loading attribute:', e);
        attribute.value = null;
    }
};

const submit = async () => {
    processing.value = true;
    errors.value = {};

    try {
        const payload = {
            name: form.value.name,
            is_active: form.value.is_active,
            values: form.value.values
                .map((v) => v.trim())
                .filter((v) => v !== ''),
        };

        const headers = { Accept: 'application/json' };

        if (props.mode === 'create') {
            const response = await axios.post('/api/attributes', payload, {
                headers,
            });
            const saved = response.data?.data as Attribute;
            emit('saved', saved);
            return;
        }

        if (!props.attributeId) return;

        const response = await axios.put(
            `/api/attributes/${props.attributeId}`,
            payload,
            { headers },
        );
        const saved = response.data?.data as Attribute;
        attribute.value = saved;
        emit('saved', saved);
    } catch (e: any) {
        if (e?.response?.status === 422) {
            errors.value = e.response.data?.errors || {};
        } else {
            console.error('Error saving attribute:', e);
        }
    } finally {
        processing.value = false;
    }
};

defineExpose({
    submit,
    processing,
});

onMounted(() => {
    if (props.mode === 'create' && props.initialName && !form.value.name) {
        form.value.name = props.initialName;
    }
    loadAttribute();
});
</script>

<template>
    <Card class="max-w-2xl">
        <CardHeader>
            <CardTitle>Información del Atributo</CardTitle>
        </CardHeader>
        <CardContent>
            <form @submit.prevent="submit" class="space-y-6">
                <div class="space-y-2">
                    <Label for="name">Nombre del Atributo *</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        placeholder="Ej: Color, Talla, Material..."
                        :class="{ 'border-destructive': errors.name }"
                        required
                    />
                    <p v-if="errors.name" class="text-sm text-destructive">
                        {{ errors.name }}
                    </p>
                </div>

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
                        Agrega al menos un valor para el atributo. Ej: Rojo,
                        Azul, Verde
                    </p>
                    <p v-if="errors.values" class="text-sm text-destructive">
                        {{ errors.values }}
                    </p>
                </div>

                <div
                    class="flex items-center justify-between rounded-lg border p-4"
                >
                    <div class="space-y-0.5">
                        <Label for="is_active">Estado Activo</Label>
                        <p class="text-sm text-muted-foreground">
                            El atributo estará disponible para asignar a
                            productos
                        </p>
                    </div>
                    <Switch id="is_active" v-model:checked="form.is_active" />
                </div>
            </form>
        </CardContent>
    </Card>
</template>
