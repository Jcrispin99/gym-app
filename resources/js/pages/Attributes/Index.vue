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
import { Edit, Plus, Power, Search, Tag, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface AttributeValue {
    id: number;
    value: string;
}

interface Attribute {
    id: number;
    name: string;
    is_active: boolean;
    attribute_values: AttributeValue[];
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Atributos', href: '/attributes' },
];

const attributes = ref<Attribute[]>([]);
const isLoading = ref(false);
const deleteDialogOpen = ref(false);
const attributeToDelete = ref<Attribute | null>(null);
const searchQuery = ref('');

const openDeleteDialog = (attribute: Attribute) => {
    attributeToDelete.value = attribute;
    deleteDialogOpen.value = true;
};

const loadAttributes = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/api/attributes', {
            params: { with_values: 1 },
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        attributes.value = (response.data?.data || []) as Attribute[];
    } catch (e) {
        console.error('Error loading attributes:', e);
        attributes.value = [];
    } finally {
        isLoading.value = false;
    }
};

const deleteAttribute = async () => {
    const target = attributeToDelete.value;
    if (!target) return;

    try {
        await axios.delete(`/api/attributes/${target.id}`, {
            headers: { Accept: 'application/json' },
        });
        attributes.value = attributes.value.filter((a) => a.id !== target.id);
    } catch (e) {
        console.error('Error deleting attribute:', e);
    } finally {
        deleteDialogOpen.value = false;
        attributeToDelete.value = null;
    }
};

const toggleStatus = async (attribute: Attribute) => {
    try {
        const response = await axios.post(
            `/api/attributes/${attribute.id}/toggle-status`,
            {},
            { headers: { Accept: 'application/json' } },
        );
        const updated = response.data?.data as Attribute;
        const index = attributes.value.findIndex((a) => a.id === attribute.id);
        if (index >= 0) {
            attributes.value.splice(index, 1, updated);
        }
    } catch (e) {
        console.error('Error toggling attribute status:', e);
    }
};

const filteredAttributes = computed(() => {
    if (!searchQuery.value) {
        return attributes.value;
    }

    const query = searchQuery.value.toLowerCase();
    return attributes.value.filter((attribute) =>
        attribute.name.toLowerCase().includes(query),
    );
});

const activeAttributes = computed(() => {
    return filteredAttributes.value.filter((a) => a.is_active);
});

const totalValues = computed(() => {
    return attributes.value.reduce(
        (sum, attr) => sum + attr.attribute_values.length,
        0,
    );
});

onMounted(loadAttributes);
</script>

<template>
    <Head title="Atributos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <FormPageHeader
                title="Atributos"
                description="Gestiona los atributos y valores de productos"
                :show-back="false"
            >
                <template #actions>
                    <Button @click="router.visit('/attributes/create')">
                        <Plus class="mr-2 h-4 w-4" />
                        Nuevo Atributo
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
                            >Total Atributos</CardTitle
                        >
                        <Tag class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ attributes.length }}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Activos</CardTitle
                        >
                        <Tag class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ activeAttributes.length }}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Total Valores</CardTitle
                        >
                        <Tag class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ totalValues }}</div>
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
                        placeholder="Buscar atributos..."
                        class="pl-8"
                    />
                </div>
            </div>

            <!-- Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Listado de Atributos</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Valores</TableHead>
                                <TableHead>Cantidad</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead class="text-right"
                                    >Acciones</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="filteredAttributes.length === 0">
                                <TableCell colspan="5" class="text-center">
                                    <span
                                        v-if="isLoading"
                                        class="inline-flex items-center gap-2"
                                    >
                                        <Spinner />
                                        Cargando...
                                    </span>
                                    <span v-else
                                        >No se encontraron atributos</span
                                    >
                                </TableCell>
                            </TableRow>
                            <TableRow
                                v-for="attribute in filteredAttributes"
                                :key="attribute.id"
                            >
                                <TableCell class="font-medium">{{
                                    attribute.name
                                }}</TableCell>
                                <TableCell>
                                    <div class="flex flex-wrap gap-1">
                                        <Badge
                                            v-for="value in attribute.attribute_values.slice(
                                                0,
                                                5,
                                            )"
                                            :key="value.id"
                                            variant="secondary"
                                            class="text-xs"
                                        >
                                            {{ value.value }}
                                        </Badge>
                                        <Badge
                                            v-if="
                                                attribute.attribute_values
                                                    .length > 5
                                            "
                                            variant="outline"
                                            class="text-xs"
                                        >
                                            +{{
                                                attribute.attribute_values
                                                    .length - 5
                                            }}
                                            más
                                        </Badge>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span class="text-sm text-muted-foreground">
                                        {{ attribute.attribute_values.length }}
                                        valores
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :class="
                                            attribute.is_active
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-gray-100 text-gray-800'
                                        "
                                    >
                                        {{
                                            attribute.is_active
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
                                            @click="toggleStatus(attribute)"
                                            :title="
                                                attribute.is_active
                                                    ? 'Desactivar'
                                                    : 'Activar'
                                            "
                                        >
                                            <Power
                                                class="h-4 w-4"
                                                :class="
                                                    attribute.is_active
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
                                                    `/attributes/${attribute.id}/edit`,
                                                )
                                            "
                                        >
                                            <Edit class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="openDeleteDialog(attribute)"
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
                        Esta acción no se puede deshacer. Se eliminará el
                        atributo
                        <strong>{{ attributeToDelete?.name }}</strong> y todos
                        sus valores.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancelar</AlertDialogCancel>
                    <AlertDialogAction @click="deleteAttribute"
                        >Eliminar</AlertDialogAction
                    >
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
