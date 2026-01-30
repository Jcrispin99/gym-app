<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';

interface Category {
    id: number;
    name: string;
    slug: string;
    full_name: string | null;
    description: string | null;
    parent_id: number | null;
    is_active: boolean;
    parent?: {
        id: number;
        name: string;
    };
}

type Mode = 'create' | 'edit';

const props = defineProps<{
    mode: Mode;
    categoryId?: number | null;
    initialName?: string;
    initialParentId?: number | null;
}>();

const emit = defineEmits<{
    (e: 'loaded', category: Category): void;
    (e: 'saved', category: Category): void;
}>();

const category = ref<Category | null>(null);
const parentCategories = ref<Array<{ id: number; name: string }>>([]);
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const form = ref({
    name: '',
    slug: '',
    full_name: '',
    description: '',
    parent_id: null as number | null,
    is_active: true,
});

const generateSlug = (text: string): string => {
    return text
        .toLowerCase()
        .trim()
        .replace(/[áàäâ]/g, 'a')
        .replace(/[éèëê]/g, 'e')
        .replace(/[íìïî]/g, 'i')
        .replace(/[óòöô]/g, 'o')
        .replace(/[úùüû]/g, 'u')
        .replace(/ñ/g, 'n')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
};

watch(
    () => form.value.name,
    (newName) => {
        if (
            !form.value.slug ||
            form.value.slug === generateSlug(form.value.name)
        ) {
            form.value.slug = generateSlug(newName);
        }
    },
);

const parentSelectValue = computed(() => {
    return form.value.parent_id === null
        ? 'root'
        : String(form.value.parent_id);
});

const handleParentChange = (value: any) => {
    if (value === 'root' || value === null || value === undefined) {
        form.value.parent_id = null;
        return;
    }
    const parsed = Number(value);
    form.value.parent_id = Number.isFinite(parsed) ? parsed : null;
};

const loadParentCategories = async () => {
    try {
        const response = await axios.get('/api/categories', {
            params: { only_active: 1 },
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const all = (response.data?.data || []) as Category[];
        const roots = all
            .filter((c) => c.parent_id === null)
            .filter((c) =>
                props.mode === 'edit' ? c.id !== props.categoryId : true,
            )
            .map((c) => ({ id: c.id, name: c.name }));

        parentCategories.value = roots;
    } catch (e) {
        console.error('Error loading categories:', e);
        parentCategories.value = [];
    }
};

const loadCategory = async () => {
    if (props.mode !== 'edit' || !props.categoryId) return;

    try {
        const response = await axios.get(
            `/api/categories/${props.categoryId}`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        category.value = response.data?.data as Category;
        if (category.value) {
            form.value = {
                name: category.value.name,
                slug: category.value.slug,
                full_name: category.value.full_name || '',
                description: category.value.description || '',
                parent_id: category.value.parent_id,
                is_active: !!category.value.is_active,
            };
            emit('loaded', category.value);
        }
    } catch (e) {
        console.error('Error loading category:', e);
        category.value = null;
    }
};

const submit = async () => {
    processing.value = true;
    errors.value = {};

    try {
        const payload = {
            name: form.value.name,
            slug: form.value.slug,
            full_name: form.value.full_name || null,
            description: form.value.description || null,
            parent_id: form.value.parent_id,
            is_active: form.value.is_active,
        };

        const headers = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };

        if (props.mode === 'create') {
            const response = await axios.post('/api/categories', payload, {
                headers,
            });
            const saved = response.data?.data as Category;
            emit('saved', saved);
            return;
        }

        if (!props.categoryId) return;

        const response = await axios.put(
            `/api/categories/${props.categoryId}`,
            payload,
            {
                headers,
            },
        );
        const saved = response.data?.data as Category;
        category.value = saved;
        emit('saved', saved);
    } catch (e: any) {
        if (e?.response?.status === 422) {
            errors.value = e.response.data?.errors || {};
        } else {
            console.error('Error saving category:', e);
        }
    } finally {
        processing.value = false;
    }
};

defineExpose({
    submit,
    processing,
});

onMounted(async () => {
    if (props.mode === 'create') {
        if (props.initialName && !form.value.name) {
            form.value.name = props.initialName;
        }
        if (
            props.initialParentId !== undefined &&
            form.value.parent_id === null
        ) {
            form.value.parent_id = props.initialParentId;
        }
    }
    await Promise.all([loadParentCategories(), loadCategory()]);
});
</script>

<template>
    <Card class="w-full">
        <CardHeader>
            <CardTitle>Información de la Categoría</CardTitle>
        </CardHeader>
        <CardContent>
            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="name">Nombre *</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        placeholder="Electrónica"
                        :class="{
                            'border-destructive': errors.name,
                        }"
                        required
                    />
                    <p v-if="errors.name" class="text-sm text-destructive">
                        {{ errors.name }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="slug">Slug *</Label>
                    <Input
                        id="slug"
                        v-model="form.slug"
                        placeholder="electronica"
                        :class="{
                            'border-destructive': errors.slug,
                        }"
                        required
                    />
                    <p class="text-xs text-muted-foreground">
                        URL amigable para la categoría
                    </p>
                    <p v-if="errors.slug" class="text-sm text-destructive">
                        {{ errors.slug }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="full_name">Nombre Completo</Label>
                    <Input
                        id="full_name"
                        v-model="form.full_name"
                        placeholder="Productos Electrónicos"
                        :class="{
                            'border-destructive': errors.full_name,
                        }"
                    />
                    <p v-if="errors.full_name" class="text-sm text-destructive">
                        {{ errors.full_name }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="parent_id">Categoría Padre</Label>
                    <Select
                        :model-value="parentSelectValue"
                        @update:model-value="handleParentChange"
                    >
                        <SelectTrigger>
                            <SelectValue
                                placeholder="Sin categoría padre (Raíz)"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="root"
                                >Sin categoría padre (Raíz)</SelectItem
                            >
                            <SelectItem
                                v-for="cat in parentCategories"
                                :key="cat.id"
                                :value="String(cat.id)"
                            >
                                {{ cat.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">
                        Selecciona una categoría padre si es una subcategoría
                    </p>
                    <p v-if="errors.parent_id" class="text-sm text-destructive">
                        {{ errors.parent_id }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="description">Descripción</Label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        placeholder="Descripción de la categoría..."
                        rows="4"
                        :class="{
                            'border-destructive': errors.description,
                        }"
                    />
                    <p
                        v-if="errors.description"
                        class="text-sm text-destructive"
                    >
                        {{ errors.description }}
                    </p>
                </div>

                <div
                    class="flex items-center justify-between rounded-lg border p-4"
                >
                    <div class="space-y-0.5">
                        <Label for="is_active">Estado Activo</Label>
                        <p class="text-sm text-muted-foreground">
                            La categoría estará disponible para asignar a
                            productos
                        </p>
                    </div>
                    <Switch id="is_active" v-model:checked="form.is_active" />
                </div>
            </form>
        </CardContent>
    </Card>
</template>
