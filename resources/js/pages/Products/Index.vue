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
import { Edit, Package, Plus, Power, Search, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface Category {
    id: number;
    name: string;
}

interface ProductProduct {
    id: number;
    sku: string | null;
    stock: number;
}

interface Product {
    id: number;
    name: string;
    description: string | null;
    price: number;
    is_active: boolean;
    category: Category;
    image: string | null;
    sku: string | null;
    barcode: string | null;
    product_products: ProductProduct[];
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Productos', href: '/products' },
];

const products = ref<Product[]>([]);
const meta = ref({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
});
const isLoading = ref(false);
const deleteDialogOpen = ref(false);
const productToDelete = ref<Product | null>(null);
const deleteError = ref<string | null>(null);
const searchQuery = ref('');
const currentPage = ref(1);

const openDeleteDialog = (product: Product) => {
    productToDelete.value = product;
    deleteError.value = null;
    deleteDialogOpen.value = true;
};

const loadProducts = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/api/product-templates', {
            params: {
                search: searchQuery.value || undefined,
                page: currentPage.value,
                per_page: meta.value.per_page,
            },
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        products.value = (response.data?.data || []) as Product[];
        meta.value = response.data?.meta || meta.value;
    } catch (e) {
        console.error('Error loading products:', e);
        products.value = [];
        meta.value = { ...meta.value, total: 0, last_page: 1, current_page: 1 };
    } finally {
        isLoading.value = false;
    }
};

const deleteProduct = async () => {
    const target = productToDelete.value;
    if (!target) return;

    deleteError.value = null;
    try {
        await axios.delete(`/api/product-templates/${target.id}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        products.value = products.value.filter((p) => p.id !== target.id);
        meta.value = { ...meta.value, total: Math.max(0, meta.value.total - 1) };
        deleteDialogOpen.value = false;
        productToDelete.value = null;
    } catch (e: any) {
        if (e?.response?.status === 422) {
            deleteError.value =
                e.response.data?.errors?.product ||
                e.response.data?.errors?.productTemplate ||
                'No se pudo eliminar.';
        } else {
            console.error('Error deleting product:', e);
            deleteError.value = 'No se pudo eliminar.';
        }
    }
};

const toggleStatus = async (product: Product) => {
    try {
        const response = await axios.post(
            `/api/product-templates/${product.id}/toggle-status`,
            {},
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );
        const updated = response.data?.data as Product;
        const index = products.value.findIndex((p) => p.id === product.id);
        if (index >= 0) {
            products.value.splice(index, 1, { ...products.value[index], ...updated });
        }
    } catch (e) {
        console.error('Error toggling product status:', e);
    }
};

const search = async () => {
    currentPage.value = 1;
    await loadProducts();
};

const canPrev = computed(() => currentPage.value > 1);
const canNext = computed(() => currentPage.value < meta.value.last_page);

const goPrev = async () => {
    if (!canPrev.value) return;
    currentPage.value -= 1;
    await loadProducts();
};

const goNext = async () => {
    if (!canNext.value) return;
    currentPage.value += 1;
    await loadProducts();
};

const getTotalStock = (product: Product): number => {
    return product.product_products.reduce((sum, pp) => sum + pp.stock, 0);
};

const formatPrice = (price: number): string => {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
    }).format(price);
};

onMounted(loadProducts);
</script>

<template>
    <Head title="Productos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <FormPageHeader
                title="Productos"
                description="Gestiona el catálogo de productos con variantes"
                :show-back="false"
            >
                <template #actions>
                    <Button @click="router.visit('/products/create')">
                        <Plus class="mr-2 h-4 w-4" />
                        Nuevo Producto
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
                            >Total Productos</CardTitle
                        >
                        <Package class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ meta.total }}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >En esta página</CardTitle
                        >
                        <Package class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ products.length }}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Página Actual</CardTitle
                        >
                        <Package class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ meta.current_page }} /
                            {{ meta.last_page }}
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
                        placeholder="Buscar por nombre, SKU, código de barras..."
                        class="pl-8"
                        @keyup.enter="search"
                    />
                </div>
                <Button @click="search">Buscar</Button>
            </div>

            <!-- Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Listado de Productos</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Imagen</TableHead>
                                <TableHead>Producto</TableHead>
                                <TableHead>Categoría</TableHead>
                                <TableHead>SKU</TableHead>
                                <TableHead>Precio</TableHead>
                                <TableHead>Stock</TableHead>
                                <TableHead>Variantes</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead class="text-right"
                                    >Acciones</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="products.length === 0">
                                <TableCell colspan="9" class="text-center">
                                    <span
                                        v-if="isLoading"
                                        class="inline-flex items-center gap-2"
                                    >
                                        <Spinner />
                                        Cargando...
                                    </span>
                                    <span v-else>No se encontraron productos</span>
                                </TableCell>
                            </TableRow>
                            <TableRow
                                v-for="product in products"
                                :key="product.id"
                            >
                                <TableCell>
                                    <img
                                        v-if="product.image"
                                        :src="product.image"
                                        :alt="product.name"
                                        class="h-12 w-12 rounded object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-12 w-12 items-center justify-center rounded bg-muted"
                                    >
                                        <Package
                                            class="h-6 w-6 text-muted-foreground"
                                        />
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="font-medium">
                                        {{ product.name }}
                                    </div>
                                    <div
                                        v-if="product.description"
                                        class="line-clamp-1 text-sm text-muted-foreground"
                                    >
                                        {{ product.description }}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span class="text-sm">{{
                                        product.category.name
                                    }}</span>
                                </TableCell>
                                <TableCell>
                                    <code
                                        v-if="product.sku"
                                        class="rounded bg-muted px-1 py-0.5 text-xs"
                                    >
                                        {{ product.sku }}
                                    </code>
                                    <span
                                        v-else
                                        class="text-sm text-muted-foreground"
                                        >-</span
                                    >
                                </TableCell>
                                <TableCell>
                                    <span class="font-medium">{{
                                        formatPrice(product.price)
                                    }}</span>
                                </TableCell>
                                <TableCell>
                                    <span
                                        :class="
                                            getTotalStock(product) > 0
                                                ? 'text-green-600'
                                                : 'text-red-600'
                                        "
                                    >
                                        {{ getTotalStock(product) }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <Badge variant="secondary">
                                        {{
                                            product.product_products.length
                                        }}
                                        variante{{
                                            product.product_products.length !==
                                            1
                                                ? 's'
                                                : ''
                                        }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :class="
                                            product.is_active
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-gray-100 text-gray-800'
                                        "
                                    >
                                        {{
                                            product.is_active
                                                ? 'Activo'
                                                : 'Inactivo'
                                        }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="toggleStatus(product)"
                                            :title="
                                                product.is_active
                                                    ? 'Desactivar'
                                                    : 'Activar'
                                            "
                                        >
                                            <Power
                                                class="h-4 w-4"
                                                :class="
                                                    product.is_active
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
                                                    `/products/${product.id}/edit`,
                                                )
                                            "
                                        >
                                            <Edit class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="openDeleteDialog(product)"
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

                    <!-- Pagination -->
                    <div class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Mostrando {{ products.length }} de
                            {{ meta.total }} productos
                        </div>
                        <div class="flex gap-1">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="!canPrev || isLoading"
                                @click="goPrev"
                            >
                                Anterior
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="!canNext || isLoading"
                                @click="goNext"
                            >
                                Siguiente
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Delete Dialog -->
        <AlertDialog v-model:open="deleteDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>¿Estás seguro?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Esta acción no se puede deshacer. Se eliminará el
                        producto
                        <strong>{{ productToDelete?.name }}</strong> y todas sus
                        variantes.
                    </AlertDialogDescription>
                    <AlertDialogDescription v-if="deleteError" class="text-destructive">
                        {{ deleteError }}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancelar</AlertDialogCancel>
                    <AlertDialogAction @click="deleteProduct"
                        >Eliminar</AlertDialogAction
                    >
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
