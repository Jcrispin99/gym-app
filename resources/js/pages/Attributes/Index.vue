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
import { Edit, Plus, Power, Search, Tag, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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

interface Props {
    attributes: Attribute[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Atributos', href: '/attributes' },
];

const deleteDialogOpen = ref(false);
const attributeToDelete = ref<Attribute | null>(null);
const searchQuery = ref('');

const openDeleteDialog = (attribute: Attribute) => {
    attributeToDelete.value = attribute;
    deleteDialogOpen.value = true;
};

const deleteAttribute = () => {
    if (attributeToDelete.value) {
        router.delete(`/attributes/${attributeToDelete.value.id}`, {
            onSuccess: () => {
                deleteDialogOpen.value = false;
                attributeToDelete.value = null;
            },
        });
    }
};

const toggleStatus = (attribute: Attribute) => {
    router.post(
        `/attributes/${attribute.id}/toggle-status`,
        {},
        {
            preserveScroll: true,
        },
    );
};

const filteredAttributes = computed(() => {
    if (!searchQuery.value) {
        return props.attributes;
    }

    const query = searchQuery.value.toLowerCase();
    return props.attributes.filter((attribute) =>
        attribute.name.toLowerCase().includes(query),
    );
});

const activeAttributes = computed(() => {
    return filteredAttributes.value.filter((a) => a.is_active);
});

const totalValues = computed(() => {
    return props.attributes.reduce(
        (sum, attr) => sum + attr.attribute_values.length,
        0,
    );
});
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
                                    No se encontraron atributos
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
                                            v-for="(
                                                value, index
                                            ) in attribute.attribute_values.slice(
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
                                        {{
                                            attribute.attribute_values.length
                                        }}
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
