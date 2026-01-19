<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { watch } from 'vue';
import type { BreadcrumbItem } from '@/types';

interface Category {
    id: number;
    name: string;
}

interface Props {
    parentCategories: Category[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Categorías', href: '/categories' },
    { title: 'Nueva Categoría', href: '/categories/create' },
];

const form = useForm({
    name: '',
    slug: '',
    full_name: '',
    description: '',
    parent_id: null as number | null,
    is_active: true,
});

// Auto-generate slug from name
watch(
    () => form.name,
    (newName) => {
        if (!form.slug || form.slug === generateSlug(form.name)) {
            form.slug = generateSlug(newName);
        }
    }
);

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

const submit = () => {
    form.post('/categories');
};
</script>

<template>
    <Head title="Nueva Categoría" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="w-full p-4">
            <div class="flex flex-col gap-4">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="icon" @click="$inertia.visit('/categories')">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Nueva Categoría</h1>
                    <p class="text-muted-foreground">Crear una nueva categoría de producto</p>
                </div>
            </div>

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
                                :class="{ 'border-destructive': form.errors.name }"
                                required
                            />
                            <p v-if="form.errors.name" class="text-sm text-destructive">
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
                                :class="{ 'border-destructive': form.errors.slug }"
                                required
                            />
                            <p class="text-xs text-muted-foreground">
                                URL amigable para la categoría
                            </p>
                            <p v-if="form.errors.slug" class="text-sm text-destructive">
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
                                :class="{ 'border-destructive': form.errors.full_name }"
                            />
                            <p v-if="form.errors.full_name" class="text-sm text-destructive">
                                {{ form.errors.full_name }}
                            </p>
                        </div>

                        <!-- Parent Category -->
                        <div class="space-y-2">
                            <Label for="parent_id">Categoría Padre</Label>
                            <Select v-model="form.parent_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Sin categoría padre (Raíz)" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="null">Sin categoría padre (Raíz)</SelectItem>
                                    <SelectItem
                                        v-for="category in parentCategories"
                                        :key="category.id"
                                        :value="category.id"
                                    >
                                        {{ category.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs text-muted-foreground">
                                Selecciona una categoría padre si es una subcategoría
                            </p>
                            <p v-if="form.errors.parent_id" class="text-sm text-destructive">
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
                                :class="{ 'border-destructive': form.errors.description }"
                            />
                            <p v-if="form.errors.description" class="text-sm text-destructive">
                                {{ form.errors.description }}
                            </p>
                        </div>

                        <!-- Is Active -->
                        <div class="flex items-center justify-between rounded-lg border p-4">
                            <div class="space-y-0.5">
                                <Label for="is_active">Estado Activo</Label>
                                <p class="text-sm text-muted-foreground">
                                    La categoría estará disponible para asignar a productos
                                </p>
                            </div>
                            <Switch id="is_active" v-model:checked="form.is_active" />
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-2">
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Guardando...' : 'Crear Categoría' }}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                @click="$inertia.visit('/categories')"
                            >
                                Cancelar
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
            </div>
        </div>
    </AppLayout>
</template>
