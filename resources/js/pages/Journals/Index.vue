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
import {
    BookOpen,
    Edit,
    Plus,
    RotateCcw,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Journal {
    id: number;
    name: string;
    code: string;
    type: string;
    is_fiscal: boolean;
    document_type_code: string | null;
    sequence: {
        id: number;
        sequence_size: number;
        next_number: number;
        step: number;
    };
    company?: {
        id: number;
        name: string;
    };
    created_at: string;
}

interface Props {
    journals: {
        data: Journal[];
    };
    filters?: {
        search?: string;
        type?: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Diarios', href: '/journals' },
];

const deleteDialogOpen = ref(false);
const journalToDelete = ref<Journal | null>(null);
const searchQuery = ref(props.filters?.search || '');

const openDeleteDialog = (journal: Journal) => {
    journalToDelete.value = journal;
    deleteDialogOpen.value = true;
};

const deleteJournal = () => {
    if (journalToDelete.value) {
        router.delete(`/journals/${journalToDelete.value.id}`, {
            onSuccess: () => {
                deleteDialogOpen.value = false;
                journalToDelete.value = null;
            },
        });
    }
};

const resetSequence = (journal: Journal) => {
    if (
        confirm(
            `¿Estás seguro de reiniciar la secuencia de "${journal.name}" a 1?`,
        )
    ) {
        router.post(
            `/journals/${journal.id}/reset-sequence`,
            {},
            {
                preserveScroll: true,
            },
        );
    }
};

const performSearch = () => {
    router.get(
        '/journals',
        { search: searchQuery.value },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const totalJournals = computed(() => props.journals.data.length);
const fiscalJournals = computed(
    () => props.journals.data.filter((j) => j.is_fiscal).length,
);
const salesJournals = computed(
    () => props.journals.data.filter((j) => j.type === 'sale').length,
);

const getTypeLabel = (type: string): string => {
    const types: Record<string, string> = {
        sale: 'Venta',
        purchase: 'Compra',
        'purchase-order': 'Orden Compra',
        quote: 'Cotización',
        cash: 'Caja',
    };
    return types[type] || type;
};

const getTypeBadgeVariant = (type: string) => {
    const variants: Record<string, any> = {
        sale: 'default',
        purchase: 'secondary',
        'purchase-order': 'outline',
        quote: 'outline',
        cash: 'secondary',
    };
    return variants[type] || 'outline';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Diarios" />

        <div class="flex flex-col gap-4 p-4">
            <FormPageHeader
                title="Diarios"
                description="Gestiona diarios contables y su numeración"
                :show-back="false"
            >
                <template #actions>
                    <Button @click="router.visit('/journals/create')">
                        <Plus class="mr-2 h-4 w-4" />
                        Nuevo Diario
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
                            >Total Diarios</CardTitle
                        >
                        <BookOpen class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ totalJournals }}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Diarios Fiscales</CardTitle
                        >
                        <BookOpen class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ fiscalJournals }}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Diarios de Venta</CardTitle
                        >
                        <BookOpen class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ salesJournals }}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Search Bar -->
            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <Search
                        class="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        placeholder="Buscar por nombre o código..."
                        class="pl-8"
                        @keyup.enter="performSearch"
                    />
                </div>
                <Button @click="performSearch">Buscar</Button>
            </div>

            <!-- Journals Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Listado de Diarios</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Código</TableHead>
                                <TableHead>Tipo</TableHead>
                                <TableHead>Próximo #</TableHead>
                                <TableHead>Fiscal</TableHead>
                                <TableHead>Cód. SUNAT</TableHead>
                                <TableHead class="text-right"
                                    >Acciones</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="journal in journals.data"
                                :key="journal.id"
                            >
                                <TableCell class="font-medium">{{
                                    journal.name
                                }}</TableCell>
                                <TableCell>
                                    <Badge variant="outline">{{
                                        journal.code
                                    }}</Badge>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="
                                            getTypeBadgeVariant(journal.type)
                                        "
                                    >
                                        {{ getTypeLabel(journal.type) }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <span class="font-mono text-sm">
                                        {{
                                            String(
                                                journal.sequence.next_number,
                                            ).padStart(
                                                journal.sequence.sequence_size,
                                                '0',
                                            )
                                        }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        v-if="journal.is_fiscal"
                                        variant="default"
                                        >Sí</Badge
                                    >
                                    <Badge v-else variant="secondary">No</Badge>
                                </TableCell>
                                <TableCell>
                                    <span
                                        v-if="journal.document_type_code"
                                        class="text-sm"
                                    >
                                        {{ journal.document_type_code }}
                                    </span>
                                    <span
                                        v-else
                                        class="text-sm text-muted-foreground"
                                        >-</span
                                    >
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            as-child
                                        >
                                            <a
                                                :href="`/journals/${journal.id}/edit`"
                                            >
                                                <Edit class="h-4 w-4" />
                                            </a>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            @click="resetSequence(journal)"
                                            title="Reiniciar secuencia"
                                        >
                                            <RotateCcw class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            @click="openDeleteDialog(journal)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <div
                        v-if="journals.data.length === 0"
                        class="py-8 text-center text-muted-foreground"
                    >
                        No se encontraron diarios
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Delete Confirmation Dialog -->
        <AlertDialog v-model:open="deleteDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>¿Estás seguro?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Esta acción eliminará el diario "{{
                            journalToDelete?.name
                        }}" permanentemente. Esta acción no se puede deshacer.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancelar</AlertDialogCancel>
                    <AlertDialogAction @click="deleteJournal"
                        >Eliminar</AlertDialogAction
                    >
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
