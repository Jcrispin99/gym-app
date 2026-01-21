<script setup lang="ts">
import FormPageHeader from '@/components/FormPageHeader.vue';
import { Button } from '@/components/ui/button';
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
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Save } from 'lucide-vue-next';

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

interface Props {
    category: Category;
    parentCategories: Category[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Categorías', href: '/categories' },
    {
        title: 'Editar Categoría',
        href: `/categories/${props.category.id}/edit`,
    },
];

const form = useForm({
    name: props.category.name,
    slug: props.category.slug,
    full_name: props.category.full_name || '',
    description: props.category.description || '',
    parent_id: props.category.parent_id,
    is_active: props.category.is_active,
});

const submit = () => {
    form.put(`/categories/${props.category.id}`);
};
</script>

<template>
    <Head :title="`Editar ${category.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="w-full p-4">
            <div class="flex flex-col gap-4">
                <FormPageHeader
                    title="Editar Categoría"
                    :description="category.name"
                    back-href="/categories"
                >
                    <template #actions>
                        <Button @click="submit" :disabled="form.processing">
                            <Save class="mr-2 h-4 w-4" />
                            {{
                                form.processing
                                    ? 'Guardando...'
                                    : 'Actualizar Categoría'
                            }}
                        </Button>
                    </template>
                </FormPageHeader>

                <!-- Form -->
                <Card class="w-full">
                    <CardHeader>
                        <CardTitle>Información de la Categoría</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit" class="space-y-4">
                            <!-- Name -->
                            <div class="space-y-2">
                                <Label for="name">Nombre *</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    placeholder="Electrónica"
                                    :class="{
                                        'border-destructive': form.errors.name,
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

                            <!-- Slug -->
                            <div class="space-y-2">
                                <Label for="slug">Slug *</Label>
                                <Input
                                    id="slug"
                                    v-model="form.slug"
                                    placeholder="electronica"
                                    :class="{
                                        'border-destructive': form.errors.slug,
                                    }"
                                    required
                                />
                                <p class="text-xs text-muted-foreground">
                                    URL amigable para la categoría
                                </p>
                                <p
                                    v-if="form.errors.slug"
                                    class="text-sm text-destructive"
                                >
                                    {{ form.errors.slug }}
                                </p>
                            </div>

                            <!-- Full Name -->
                            <div class="space-y-2">
                                <Label for="full_name">Nombre Completo</Label>
                                <Input
                                    id="full_name"
                                    v-model="form.full_name"
                                    placeholder="Productos Electrónicos"
                                    :class="{
                                        'border-destructive':
                                            form.errors.full_name,
                                    }"
                                />
                                <p
                                    v-if="form.errors.full_name"
                                    class="text-sm text-destructive"
                                >
                                    {{ form.errors.full_name }}
                                </p>
                            </div>

                            <!-- Parent Category -->
                            <div class="space-y-2">
                                <Label for="parent_id">Categoría Padre</Label>
                                <Select v-model="form.parent_id">
                                    <SelectTrigger>
                                        <SelectValue
                                            placeholder="Sin categoría padre (Raíz)"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="null"
                                            >Sin categoría padre
                                            (Raíz)</SelectItem
                                        >
                                        <SelectItem
                                            v-for="cat in parentCategories"
                                            :key="cat.id"
                                            :value="cat.id"
                                        >
                                            {{ cat.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p class="text-xs text-muted-foreground">
                                    Selecciona una categoría padre si es una
                                    subcategoría
                                </p>
                                <p
                                    v-if="form.errors.parent_id"
                                    class="text-sm text-destructive"
                                >
                                    {{ form.errors.parent_id }}
                                </p>
                            </div>

                            <!-- Description -->
                            <div class="space-y-2">
                                <Label for="description">Descripción</Label>
                                <Textarea
                                    id="description"
                                    v-model="form.description"
                                    placeholder="Descripción de la categoría..."
                                    rows="4"
                                    :class="{
                                        'border-destructive':
                                            form.errors.description,
                                    }"
                                />
                                <p
                                    v-if="form.errors.description"
                                    class="text-sm text-destructive"
                                >
                                    {{ form.errors.description }}
                                </p>
                            </div>

                            <!-- Is Active -->
                            <div
                                class="flex items-center justify-between rounded-lg border p-4"
                            >
                                <div class="space-y-0.5">
                                    <Label for="is_active">Estado Activo</Label>
                                    <p class="text-sm text-muted-foreground">
                                        La categoría estará disponible para
                                        asignar a productos
                                    </p>
                                </div>
                                <Switch
                                    id="is_active"
                                    v-model:checked="form.is_active"
                                />
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
