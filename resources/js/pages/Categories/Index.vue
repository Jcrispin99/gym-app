<script setup lang="ts">
import FormPageHeader from '@/components/FormPageHeader.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Edit, Folder, Plus, Power, Search, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

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
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Categorías', href: '/categories' },
];

const categories = ref<Category[]>([]);
const isLoading = ref(false);
const deleteDialogOpen = ref(false);
const categoryToDelete = ref<Category | null>(null);
const deleteError = ref<string | null>(null);
const searchQuery = ref('');

const openDeleteDialog = (category: Category) => {
    categoryToDelete.value = category;
    deleteError.value = null;
    deleteDialogOpen.value = true;
};

const loadCategories = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/api/categories', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        categories.value = (response.data?.data || []) as Category[];
    } catch (e) {
        console.error('Error loading categories:', e);
        categories.value = [];
    } finally {
        isLoading.value = false;
    }
};

const deleteCategory = async () => {
    const target = categoryToDelete.value;
    if (!target) return;

    deleteError.value = null;
    try {
        await axios.delete(`/api/categories/${target.id}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        categories.value = categories.value.filter((c) => c.id !== target.id);
        deleteDialogOpen.value = false;
        categoryToDelete.value = null;
    } catch (e: any) {
        if (e?.response?.status === 422) {
            deleteError.value =
                e.response.data?.errors?.category || 'No se pudo eliminar.';
        } else {
            console.error('Error deleting category:', e);
            deleteError.value = 'No se pudo eliminar.';
        }
    }
};

const toggleStatus = async (category: Category) => {
    try {
        const response = await axios.post(
            `/api/categories/${category.id}/toggle-status`,
            {},
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );
        const updated = response.data?.data as Category;
        const index = categories.value.findIndex((c) => c.id === category.id);
        if (index >= 0) {
            categories.value.splice(index, 1, updated);
        }
    } catch (e) {
        console.error('Error toggling category status:', e);
    }
};

const filteredCategories = computed(() => {
    if (!searchQuery.value) {
        return categories.value;
    }

    const query = searchQuery.value.toLowerCase();
    return categories.value.filter(
        (category) =>
            category.name.toLowerCase().includes(query) ||
            category.slug.toLowerCase().includes(query) ||
            category.description?.toLowerCase().includes(query),
    );
});

const rootCategories = computed(() => {
    return filteredCategories.value.filter((c) => !c.parent_id);
});

const activeCategories = computed(() => {
    return filteredCategories.value.filter((c) => c.is_active);
});

onMounted(loadCategories);
</script>

<template>
    <Head title="Categorías" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <FormPageHeader
                title="Categorías"
                description="Gestiona las categorías de productos del inventario"
                :show-back="false"
            >
                <template #actions>
                    <Button @click="router.visit('/categories/create')">
                        <Plus class="mr-2 h-4 w-4" />
                        Nueva Categoría
                    </Button>
                </template>
            </FormPageHeader>

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Total Categorías</CardTitle
                        >
                        <Folder class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ categories.length }}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Categorías Raíz</CardTitle
                        >
                        <Folder class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ rootCategories.length }}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Activas</CardTitle
                        >
                        <Folder class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ activeCategories.length }}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Search -->
            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <Search
                        class="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        placeholder="Buscar categorías..."
                        class="pl-8"
                    />
                </div>
            </div>

            <!-- Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Listado de Categorías</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Slug</TableHead>
                                <TableHead>Categoría Padre</TableHead>
                                <TableHead>Descripción</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead class="text-right"
                                    >Acciones</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="filteredCategories.length === 0">
                                <TableCell colspan="6" class="text-center">
                                    <span
                                        v-if="isLoading"
                                        class="inline-flex items-center gap-2"
                                    >
                                        <Spinner />
                                        Cargando...
                                    </span>
                                    <span v-else>No se encontraron categorías</span>
                                </TableCell>
                            </TableRow>
                            <TableRow
                                v-for="category in filteredCategories"
                                :key="category.id"
                            >
                                <TableCell class="font-medium">{{
                                    category.name
                                }}</TableCell>
                                <TableCell>
                                    <code
                                        class="rounded bg-muted px-1 py-0.5 text-xs"
                                        >{{ category.slug }}</code
                                    >
                                </TableCell>
                                <TableCell>
                                    <span
                                        v-if="category.parent"
                                        class="text-sm text-muted-foreground"
                                    >
                                        {{ category.parent.name }}
                                    </span>
                                    <span
                                        v-else
                                        class="text-sm text-muted-foreground"
                                        >-</span
                                    >
                                </TableCell>
                                <TableCell>
                                    <span
                                        class="line-clamp-1 text-sm text-muted-foreground"
                                    >
                                        {{ category.description || '-' }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :class="
                                            category.is_active
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-gray-100 text-gray-800'
                                        "
                                    >
                                        {{
                                            category.is_active
                                                ? 'Activa'
                                                : 'Inactiva'
                                        }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="toggleStatus(category)"
                                            :title="
                                                category.is_active
                                                    ? 'Desactivar'
                                                    : 'Activar'
                                            "
                                        >
                                            <Power
                                                class="h-4 w-4"
                                                :class="
                                                    category.is_active
                                                        ? 'text-green-600'
                                                        : 'text-gray-400'
                                                "
                                            />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="
                                                router.visit(
                                                    `/categories/${category.id}/edit`,
                                                )
                                            "
                                        >
                                            <Edit class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="openDeleteDialog(category)"
                                        >
                                            <Trash2
                                                class="h-4 w-4 text-destructive"
                                            />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>

        <!-- Delete Dialog -->
        <AlertDialog v-model:open="deleteDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>¿Estás seguro?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Esta acción no se puede deshacer. Se eliminará la
                        categoría
                        <strong>{{ categoryToDelete?.name }}</strong
                        >.
                    </AlertDialogDescription>
                    <AlertDialogDescription v-if="deleteError" class="text-destructive">
                        {{ deleteError }}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancelar</AlertDialogCancel>
                    <AlertDialogAction @click="deleteCategory"
                        >Eliminar</AlertDialogAction
                    >
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
