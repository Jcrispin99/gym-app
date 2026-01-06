<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Head, router } from '@inertiajs/vue3';
import { Plus, Edit, Trash2, Package, Search, Power } from 'lucide-vue-next';
import { ref, computed } from 'vue';

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

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    products: {
        data: Product[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginationLink[];
    };
}

const props = defineProps<Props>();

const deleteDialogOpen = ref(false);
const productToDelete = ref<Product | null>(null);
const searchQuery = ref('');

const openDeleteDialog = (product: Product) => {
    productToDelete.value = product;
    deleteDialogOpen.value = true;
};

const deleteProduct = () => {
    if (productToDelete.value) {
        router.delete(`/products/${productToDelete.value.id}`, {
            onSuccess: () => {
                deleteDialogOpen.value = false;
                productToDelete.value = null;
            },
        });
    }
};

const toggleStatus = (product: Product) => {
    router.post(
        `/products/${product.id}/toggle-status`,
        {},
        {
            preserveScroll: true,
        }
    );
};

const search = () => {
    router.get(
        '/products',
        { search: searchQuery.value },
        {
            preserveState: true,
            preserveScroll: true,
        }
    );
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
</script>

<template>
    <Head title="Productos" />

    <AppLayout>
        <div class="flex flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Productos</h1>
                    <p class="text-muted-foreground">
                        Gestiona el catálogo de productos con variantes
                    </p>
                </div>
                <Button @click="router.visit('/products/create')">
                    <Plus class="mr-2 h-4 w-4" />
                    Nuevo Producto
                </Button>
            </div>

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">Total Productos</CardTitle>
                        <Package class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ products.total }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">En esta página</CardTitle>
                        <Package class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ products.data.length }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">Página Actual</CardTitle>
                        <Package class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ products.current_page }} / {{ products.last_page }}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Search -->
            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
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
                                <TableHead class="text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="products.data.length === 0">
                                <TableCell colspan="9" class="text-center">
                                    No se encontraron productos
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="product in products.data" :key="product.id">
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
                                        <Package class="h-6 w-6 text-muted-foreground" />
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="font-medium">{{ product.name }}</div>
                                    <div
                                        v-if="product.description"
                                        class="text-sm text-muted-foreground line-clamp-1"
                                    >
                                        {{ product.description }}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span class="text-sm">{{ product.category.name }}</span>
                                </TableCell>
                                <TableCell>
                                    <code
                                        v-if="product.sku"
                                        class="rounded bg-muted px-1 py-0.5 text-xs"
                                    >
                                        {{ product.sku }}
                                    </code>
                                    <span v-else class="text-sm text-muted-foreground">-</span>
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
                                        {{ product.product_products.length }} variante{{
                                            product.product_products.length !== 1 ? 's' : ''
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
                                        {{ product.is_active ? 'Activo' : 'Inactivo' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="toggleStatus(product)"
                                            :title="
                                                product.is_active ? 'Desactivar' : 'Activar'
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
                                            @click="router.visit(`/products/${product.id}/edit`)"
                                        >
                                            <Edit class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="openDeleteDialog(product)"
                                        >
                                            <Trash2 class="h-4 w-4 text-destructive" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <!-- Pagination -->
                    <div class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Mostrando {{ products.data.length }} de {{ products.total }} productos
                        </div>
                        <div class="flex gap-1">
                            <Button
                                v-for="link in products.links"
                                :key="link.label"
                                :variant="link.active ? 'default' : 'outline'"
                                size="sm"
                                :disabled="!link.url"
                                @click="link.url && router.visit(link.url)"
                                v-html="link.label"
                            />
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
                        Esta acción no se puede deshacer. Se eliminará el producto
                        <strong>{{ productToDelete?.name }}</strong> y todas sus variantes.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancelar</AlertDialogCancel>
                    <AlertDialogAction @click="deleteProduct">Eliminar</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
